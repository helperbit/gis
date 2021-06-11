<?php
/* 
 *  Helperbit: a p2p donation platform (gis)

 *  Copyright (C) 2016-2021  Helperbit team
 *  
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *  
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *  
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>
 */

<?php

include_once __DIR__ . '/gestioneDb.php';
include_once __DIR__ . '/emailSender.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');

$magMin = 5.5;
$magMax = 11;
$connection = connetti();

$stringaQuery = "select count(*) as conta ,array_agg(code) as codici
  FROM world_earthquakes_1900_55";
$result = query($connection, $stringaQuery);
$row = $result->fetch(PDO::FETCH_ASSOC);
$num_record_prima = $row["conta"];
$lista_codici = $row["codici"];
//echo $lista_codici;
$where = str_replace("{", "'", $lista_codici);
$where = str_replace(",", "','", $where);
$where = str_replace("}", "'", $where);
$d = strtotime("-5 Day");
$twomonthsago = date("Y-m-d", $d) . "T00:00:00";
//$twomonthsago = "2015-01-01T00:00:00";
//

$query = "http://earthquake.usgs.gov/fdsnws/event/1/query?starttime=$twomonthsago&format=geojson&minmagnitude=$magMin&maxmagnitude=$magMax";

$esegui = 'ogr2ogr -f PostgreSQL PG:"host=' . $host . ' user=' . $user . ' port=' . $port . ' dbname=' . $dbName . ' password=' . $password . '" "' . $query . '" -skipfailures -append -nln world_earthquakes_1900_55';
//echo $esegui;
exec($esegui);

$stringaQuery = "select count(*) as conta 
  FROM world_earthquakes_1900_55";
$result = query($connection, $stringaQuery);
$row = $result->fetch(PDO::FETCH_ASSOC);
$num_record_dopo = $row["conta"];
$num_record_inseriti = $num_record_dopo - $num_record_prima;
echo "Sono state inserite $num_record_inseriti righe in tabella <br> \n";

$stringaQuery = 'select ogc_fid, st_transform(wkb_geometry,900913) as geom, st_buffer(st_transform(wkb_geometry,900913),(mag * 8000)) as buffer_geom_900913, st_buffer(st_transform(wkb_geometry,3857),(mag * 8000)) as buffer_geom_3857, mag, place, "time", updated, tz, url, 
       detail, felt, cdi, mmi, alert, status, tsunami, sig, net, code, st_z(wkb_geometry) as depth,
       ids, sources, types, nst, dmin, rms, gap, magtype, type, title, id_evento  
  FROM world_earthquakes_1900_55 
  where is_lavorato is not True
  order by "time" ASC  
  --limit 100';
$result = query($connection, $stringaQuery);
echo "Inizio creazione eventi <br> \n";
if ($result->rowCount() > 0) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $usgs = $row["net"] . $row["code"];
        $placeusgs = $row["place"];
        $isFiji = false;
        $isNZ = false;
        if (strpos($placeusgs, 'Fiji') !== false) {
            $isFiji = true;
        } elseif (strpos($placeusgs, 'New Zealand') !== false) {
            $isNZ = true;
        }

        $ogc_fid = $row["ogc_fid"];
        $epicentro_900913 = $row["geom"];
        $buffer_geom_900913 = $row["buffer_geom_900913"];
        $buffer_geom_3857 = $row["buffer_geom_3857"];
        $time = $row["time"];
        $mag = $row["mag"];
        $depth = $row["depth"];
        $tsunami = $row["tsunami"] == 1 ? 'true' : 'false';
        $stringaQuery2 = "SELECT typical_depth,inviluppo_buffer_evento,id_evento,max_magnitude,eventi_terremoto.last_shake_time,eventi_terremoto.start_time,n_shakes, TO_TIMESTAMP($time/1000) as new_time from eventi_terremoto where st_dwithin('$buffer_geom_900913'::geometry,eventi_terremoto.inviluppo_buffer_evento, 30000) and (eventi_terremoto.last_shake_time + interval '90 days') > TO_TIMESTAMP($time/1000) and (eventi_terremoto.last_shake_time - interval '90 days') < TO_TIMESTAMP($time/1000)";
        //echo $stringaQuery2;
        $result2 = query($connection, $stringaQuery2);
        if ($result2->rowCount() > 0) { //se esiste evento
            $row2 = $result2->fetch(PDO::FETCH_ASSOC);
            $last_shake_time = $row2["last_shake_time"];
            $n_shakes = $row2["n_shakes"] + 1;
            $typical_depth = (($row2["typical_depth"] * $row2["n_shakes"]) + $depth) / $n_shakes;
            $new_time = $row2["new_time"];
            $start_time = $row2["start_time"];
            $inviluppo_buffer_evento = $row2["inviluppo_buffer_evento"];
            if ($new_time > $last_shake_time) {
                $time = $new_time;
            } else {
                $time = $last_shake_time;
            }
            if ($new_time < $start_time) {
                $new_start_time = $new_time;
            } else {
                $new_start_time = $start_time;
            }
            $id_evento = $row2["id_evento"];
            //$country_orig = $row2["country"];
            $max_magnitude = $row2["max_magnitude"];
            if ($mag < $max_magnitude) { //se evento non predominante aggiorna last_shake_time e inviluppo
                $stringaShake = "UPDATE eventi_terremoto set typical_depth = $typical_depth, n_shakes = $n_shakes, start_time = '$new_start_time', last_shake_time = '$time', inviluppo_buffer_evento = ST_Multi(st_union(ST_MakeValid(inviluppo_buffer_evento), ST_MakeValid('$buffer_geom_900913'::geometry))), usgs_name_all = array_append(usgs_name_all, '$usgs') WHERE id_evento =  $id_evento RETURNING st_transform(st_union(inviluppo_buffer_evento, '$buffer_geom_900913'::geometry),3857) as new_inviluppo_3857";
                $resultShake = query($connection, $stringaShake);
                $rowShake = $resultShake->fetch();
                $new_inviluppo_3857 = $rowShake["new_inviluppo_3857"];
            } else { //se evento predominante, aggiorna inoltre max_magnitude, max_epicentro,citta_piu_vicina,country,regione
                $stringaUpdateEvPredPlacca = "SELECT code FROM placche
                                where ST_Intersects(ST_MakeValid(st_transform('$epicentro_900913'::geometry,4326)),placche.geom)";
                $resultUpdateEvPredPlacca = query($connection, $stringaUpdateEvPredPlacca);
                //echo $stringaUpdateEvPred;
                $rowUpdateEvPredPlacca = $resultUpdateEvPredPlacca->fetch(PDO::FETCH_ASSOC);
                $placca_ricadente = pg_escape_string($rowUpdateEvPredPlacca["code"]);
                if (!$placca_ricadente)
                    $placca_ricadente = "";

                $stringaUpdateEvPredPlaccaSec = "SELECT ST_Distance(ST_MakeValid('$epicentro_900913'::geometry),st_transform(faglie.geom,900913)) as distanza_faglia, name as nome_faglia  from faglie 
                                order by ST_Distance(ST_MakeValid('$epicentro_900913'::geometry),st_transform(faglie.geom,900913))";
                $resultUpdateEvPredPlaccaSec = query($connection, $stringaUpdateEvPredPlaccaSec);
                //echo $stringaUpdateEvPred;
                $rowUpdateEvPredPlaccaSec = $resultUpdateEvPredPlaccaSec->fetch(PDO::FETCH_ASSOC);
                $distanza_faglia = pg_escape_string($rowUpdateEvPredPlaccaSec["distanza_faglia"]);
                $nome_faglia = pg_escape_string($rowUpdateEvPredPlaccaSec["nome_faglia"]);
                if (!$distanza_faglia) {
                    $distanza_faglia = 999999999;
                    $nome_faglia = '';
                } else {
                    $placca_secondaria = str_replace($placca_ricadente, "", $nome_faglia);
                    $placca_secondaria = str_replace("/", "", $placca_secondaria);
                    $placca_secondaria = str_replace("-", "", $placca_secondaria);
                    $placca_secondaria = str_replace("\\", "", $placca_secondaria);
                }
                $stringaUpdateEvPredCountry = "SELECT iso_3digit as country,is_sea, sea
                                from world_bound
                                where ST_Intersects(ST_MakeValid(st_transform(ST_Multi(st_union(ST_MakeValid('$inviluppo_buffer_evento'::geometry), ST_MakeValid('$buffer_geom_900913'::geometry))),4326)),world_bound.geom) order by is_sea limit 1";
                $resultUpdateEvPredCountry = query($connection, $stringaUpdateEvPredCountry);
                //echo $stringaUpdateEvPred;
                $rowUpdateEvPredCountry = $resultUpdateEvPredCountry->fetch(PDO::FETCH_ASSOC);
                $country = pg_escape_string($rowUpdateEvPredCountry["country"]);
                if (!$country)
                    $country = "";
                $is_sea = $rowUpdateEvPredCountry["is_sea"];
                $sea = '';
                if ($is_sea) {
                    $is_sea = "true";
                    $sea = pg_escape_string($rowUpdateEvPredCountry["sea"]);
                } else {
                    //echo "ciao";
                    $is_sea = "false";
                }


                $stringaUpdateEvPred = "SELECT regions.sr_adm0_a3 as country, regions.name as regione  from regions
                                where ST_Intersects(ST_MakeValid(st_transform('$epicentro_900913'::geometry,3857)),regions.geom)";
                $resultUpdateEvPred = query($connection, $stringaUpdateEvPred);
                //echo $stringaUpdateEvPred;
                $rowUpdateEvPred = $resultUpdateEvPred->fetch(PDO::FETCH_ASSOC);

                $regione = pg_escape_string($rowUpdateEvPred["regione"]);
                if (!$regione)
                    $regione = $sea;
                $stringaUpdateEvPred2 = "SELECT coalesce(name1,'') ||', '|| coalesce(name2,'') ||', '|| coalesce(name3,'') ||', '|| coalesce(name4,'') ||', '|| coalesce(name5,'') as citta_piu_vicina , degrees(ST_Azimuth(centroids.geom, ST_MakeValid(st_transform('$epicentro_900913'::geometry,3857)))) as direzione_citta_piu_vicina, "
                        . " ST_Distance(centroids.geom, ST_MakeValid(st_transform('$epicentro_900913'::geometry,3857))) as distanza_citta_piu_vicina, centroids.p10a as pop_citta_piu_vicina 
                                from centroids where centroids.p10a > 100000 order by ST_Distance(centroids.geom,ST_MakeValid(st_transform('$epicentro_900913'::geometry,3857))) ASC limit 1";
                $resultUpdateEvPred2 = query($connection, $stringaUpdateEvPred2);
                $rowUpdateEvPred2 = $resultUpdateEvPred2->fetch(PDO::FETCH_ASSOC);
                $citta_piu_vicina = pg_escape_string($rowUpdateEvPred2["citta_piu_vicina"]);
                $pop_citta_piu_vicina = pg_escape_string($rowUpdateEvPred2["pop_citta_piu_vicina"]);
                $direzione_citta_piu_vicina = pg_escape_string($rowUpdateEvPred2["direzione_citta_piu_vicina"]);
                if (!$citta_piu_vicina)
                    $citta_piu_vicina = "";
                $distanza_citta_piu_vicina = $rowUpdateEvPred2["distanza_citta_piu_vicina"];
                if (!$distanza_citta_piu_vicina)
                    $distanza_citta_piu_vicina = Null;
                if (!$pop_citta_piu_vicina)
                    $pop_citta_piu_vicina = Null;
                if (!$direzione_citta_piu_vicina)
                    $direzione_citta_piu_vicina = Null;

                ///////////////
                $stringaGetSecondCity = "SELECT coalesce(name1,'') ||', '|| coalesce(name2,'') ||', '|| coalesce(name3,'') ||', '|| coalesce(name4,'') ||', '|| coalesce(name5,'') as abitato_piu_vicino ,  
                         ST_Distance(centroids.geom, ST_MakeValid(st_transform('$epicentro_900913'::geometry,3857))) as distanza_abitato_piu_vicino 
                                from centroids order by ST_Distance(centroids.geom,ST_MakeValid(st_transform('$epicentro_900913'::geometry,3857))) ASC limit 1";
                $resultGetSecondCity = query($connection, $stringaGetSecondCity);
                $rowGetSecondCity = $resultGetSecondCity->fetch(PDO::FETCH_ASSOC);
                $abitato_piu_vicino = pg_escape_string($rowGetSecondCity["abitato_piu_vicino"]);
                if (!$abitato_piu_vicino)
                    $abitato_piu_vicino = "";
                $distanza_abitato_piu_vicino = $rowGetSecondCity["distanza_abitato_piu_vicino"];
                if (!$distanza_abitato_piu_vicino)
                    $distanza_abitato_piu_vicino = Null;
                ///////////////

                $stringaShake = "UPDATE eventi_terremoto set typical_depth = $typical_depth, n_shakes = $n_shakes, direzione_citta_piu_vicina = $direzione_citta_piu_vicina, distanza_citta_piu_vicina = '$distanza_citta_piu_vicina', pop_citta_piu_vicina = $pop_citta_piu_vicina, abitato_piu_vicino= '$abitato_piu_vicino' , distanza_abitato_piu_vicino= $distanza_abitato_piu_vicino , "
                        . "citta_piu_vicina = '$citta_piu_vicina', placca_ricadente = '$placca_ricadente', placca_secondaria = '$placca_secondaria',distanza_faglia = $distanza_faglia,"
                        . "country = '$country', regione = '$regione', start_time = '$new_start_time', last_shake_time = '$time',max_epicentro = '$epicentro_900913', is_sea = $is_sea, usgs_name_max = '$usgs',"
                        . "inviluppo_buffer_evento = ST_Multi(st_union(ST_MakeValid(inviluppo_buffer_evento), ST_MakeValid('$buffer_geom_900913'::geometry))), max_magnitude = $mag, tsunami = $tsunami, usgs_name_all = array_append(usgs_name_all, '$usgs') WHERE id_evento =  $id_evento "
                        . " RETURNING st_transform(st_union(inviluppo_buffer_evento, '$buffer_geom_900913'::geometry),3857) as new_inviluppo_3857";
                $resultShake = query($connection, $stringaShake);
                //echo $stringaShake;
                $rowShake = $resultShake->fetch(PDO::FETCH_ASSOC);
                $new_inviluppo_3857 = $rowShake["new_inviluppo_3857"];
            }
            //calcolo pop
            $stringaUpdateInviluppoPop = "SELECT  (stats).sum as sum_pop
                FROM (SELECT ST_SummaryStats((ST_Clip(population.rast,ST_MakeValid('$new_inviluppo_3857'::geometry)))) As stats
                    FROM population
                                where ST_Intersects(ST_MakeValid('$new_inviluppo_3857'::geometry),population.rast) 
                 ) As foo";
            $resultUpdateInviluppoPop = query($connection, $stringaUpdateInviluppoPop);
            $rowUpdateInviluppoPop = $resultUpdateInviluppoPop->fetch(PDO::FETCH_ASSOC);
            $sum_pop = $rowUpdateInviluppoPop["sum_pop"];
            if (!$sum_pop)
                $sum_pop = 0;

            //calcolo num_com
            $stringaUpdateInviluppoCom = "SELECT  count(name_3) as num_com from (
                SELECT  name_3, 0 as mioCampo
                                FROM gadm2
                                where ST_Intersects(ST_MakeValid(st_transform(st_setsrid('$new_inviluppo_3857'::geometry,3857),4326)),gadm2.geom) 
                   ) as ciao    group by mioCampo
                 ";
            $resultUpdateInviluppoCom = query($connection, $stringaUpdateInviluppoCom);
            $rowUpdateInviluppoCom = $resultUpdateInviluppoCom->fetch(PDO::FETCH_ASSOC);
            $num_com = $rowUpdateInviluppoCom["num_com"];
            if (!$num_com)
                $num_com = 0;

            //calcolo affected_countries
            if (!$isFiji && !$isNZ) {
                $stringaUpdateInviluppoCountries = "SELECT array_agg(DISTINCT iso_3digit) as affected_countries
                                from world_bound
                                where ST_Intersects(ST_MakeValid(st_transform(st_setsrid('$new_inviluppo_3857'::geometry,3857),4326)),world_bound.geom)  
                 ";
                $resultUpdateInviluppoCountries = query($connection, $stringaUpdateInviluppoCountries);
                $rowUpdateInviluppoCountries = $resultUpdateInviluppoCountries->fetch(PDO::FETCH_ASSOC);
                $affected_countries = $rowUpdateInviluppoCountries["affected_countries"];
                if (!$affected_countries)
                    $affected_countries = '{}';
            }elseif ($isFiji) {
                $affected_countries = '{FJI}';
            } elseif ($isNZ) {
                $affected_countries = '{NZL}';
            }
            $stringaShake2 = "UPDATE eventi_terremoto set popolazione_coinvolta = $sum_pop, num_comuni = $num_com, affected_countries = '$affected_countries'  WHERE id_evento =  $id_evento";
            $resultShake2 = query($connection, $stringaShake2);
            echo "Evento $id_evento aggiornato <br> \n";
            $body = "Il terremoto $usgs - $placeusgs è stato aggiunto all'evento $id_evento. <br>"
                    . " Link usgs: <a href='https://earthquake.usgs.gov/earthquakes/eventpage/$usgs'>https://earthquake.usgs.gov/earthquakes/eventpage/$usgs</a> ;<br>  affected countries: $affected_countries; ";
            email_sender("NEW SYSTEM: Aggiornato evento $id_evento", $body);
        } else { //se evento non nesiste
            // inserisce nuova riga evento e lancia get_info_epicentro  per aggiornare i relativi campi. 
            // Calcola get_inviluppo_buffer ed utilizza il risultato per calcolare get_info_epicentro e confronta_passato
            $stringaUpdateEvPredPlacca = "SELECT code FROM placche
                                where ST_Intersects(ST_MakeValid(st_transform('$epicentro_900913'::geometry,4326)),placche.geom)";
            $resultUpdateEvPredPlacca = query($connection, $stringaUpdateEvPredPlacca);
            //echo $stringaUpdateEvPred;
            $rowUpdateEvPredPlacca = $resultUpdateEvPredPlacca->fetch(PDO::FETCH_ASSOC);
            $placca_ricadente = pg_escape_string($rowUpdateEvPredPlacca["code"]);
            if (!$placca_ricadente)
                $placca_ricadente = "";

            $stringaUpdateEvPredPlaccaSec = "SELECT ST_Distance(ST_MakeValid('$epicentro_900913'::geometry),st_transform(faglie.geom,900913)) as distanza_faglia, name as nome_faglia  from faglie 
                                order by ST_Distance(ST_MakeValid('$epicentro_900913'::geometry),st_transform(faglie.geom,900913))";
            $resultUpdateEvPredPlaccaSec = query($connection, $stringaUpdateEvPredPlaccaSec);
            //echo $stringaUpdateEvPredPlaccaSec;

            $rowUpdateEvPredPlaccaSec = $resultUpdateEvPredPlaccaSec->fetch(PDO::FETCH_ASSOC);
            $distanza_faglia = pg_escape_string($rowUpdateEvPredPlaccaSec["distanza_faglia"]);
            $nome_faglia = pg_escape_string($rowUpdateEvPredPlaccaSec["nome_faglia"]);
            if (!$distanza_faglia) {
                $distanza_faglia = 999999999;
                $nome_faglia = '';
            } else {
                $placca_secondaria = str_replace($placca_ricadente, "", $nome_faglia);
                $placca_secondaria = str_replace("/", "", $placca_secondaria);
                $placca_secondaria = str_replace("-", "", $placca_secondaria);
                $placca_secondaria = str_replace("\\", "", $placca_secondaria);
            }

            $stringaUpdateEvPredCountry = "SELECT iso_3digit as country,is_sea, sea
                                from world_bound
                                where ST_Intersects(ST_MakeValid(st_transform(ST_MakeValid('$buffer_geom_900913'::geometry),4326)),world_bound.geom) order by is_sea limit 1";
            $resultUpdateEvPredCountry = query($connection, $stringaUpdateEvPredCountry);
            //echo $stringaUpdateEvPredCountry;
            $rowUpdateEvPredCountry = $resultUpdateEvPredCountry->fetch(PDO::FETCH_ASSOC);
            $country = pg_escape_string($rowUpdateEvPredCountry["country"]);
            if (!$country)
                $country = "";
            $is_sea = $rowUpdateEvPredCountry["is_sea"];
            $sea = '';
            if ($is_sea) {
                $is_sea = "true";
                $sea = pg_escape_string($rowUpdateEvPredCountry["sea"]);
            } else {
                //echo "ciao";
                $is_sea = "false";
            }

            $stringaUpdateEvPred = "SELECT regions.sr_adm0_a3 as country, regions.name as regione  from regions
                                where ST_Intersects(ST_MakeValid(st_transform('$epicentro_900913'::geometry,3857)),regions.geom)";
            //echo $stringaUpdateEvPred;
            $resultUpdateEvPred = query($connection, $stringaUpdateEvPred);

            $rowUpdateEvPred = $resultUpdateEvPred->fetch(PDO::FETCH_ASSOC);

            $regione = pg_escape_string($rowUpdateEvPred["regione"]);
            if (!$regione)
                $regione = $sea;
            $stringaUpdateEvPred2 = "SELECT coalesce(name1,'') ||', '|| coalesce(name2,'') ||', '|| coalesce(name3,'') ||', '|| coalesce(name4,'') ||', '|| coalesce(name5,'')  as citta_piu_vicina , degrees(ST_Azimuth(centroids.geom, ST_MakeValid(st_transform('$epicentro_900913'::geometry,3857)))) as direzione_citta_piu_vicina, "
                    . "ST_Distance(centroids.geom,ST_MakeValid(st_transform('$epicentro_900913'::geometry,3857))) as distanza_citta_piu_vicina, centroids.p10a as pop_citta_piu_vicina  
                                from centroids where centroids.p10a > 100000  order by ST_Distance(centroids.geom,ST_MakeValid(st_transform('$epicentro_900913'::geometry,3857))) ASC limit 1";
            //echo $stringaUpdateEvPred2;
            $resultUpdateEvPred2 = query($connection, $stringaUpdateEvPred2);
            $rowUpdateEvPred2 = $resultUpdateEvPred2->fetch(PDO::FETCH_ASSOC);
            $citta_piu_vicina = pg_escape_string($rowUpdateEvPred2["citta_piu_vicina"]);
            $pop_citta_piu_vicina = pg_escape_string($rowUpdateEvPred2["pop_citta_piu_vicina"]);
            $direzione_citta_piu_vicina = pg_escape_string($rowUpdateEvPred2["direzione_citta_piu_vicina"]);
            if (!$citta_piu_vicina)
                $citta_piu_vicina = "";
            $distanza_citta_piu_vicina = $rowUpdateEvPred2["distanza_citta_piu_vicina"];
            if (!$distanza_citta_piu_vicina)
                $distanza_citta_piu_vicina = Null;
            if (!$pop_citta_piu_vicina)
                $pop_citta_piu_vicina = Null;
            if (!$direzione_citta_piu_vicina)
                $direzione_citta_piu_vicina = Null;

            ///////////////
            $stringaGetSecondCity = "SELECT coalesce(name1,'') ||', '|| coalesce(name2,'') ||', '|| coalesce(name3,'') ||', '|| coalesce(name4,'') ||', '|| coalesce(name5,'') as abitato_piu_vicino ,  
                         ST_Distance(centroids.geom, ST_MakeValid(st_transform('$epicentro_900913'::geometry,3857))) as distanza_abitato_piu_vicino 
                                from centroids order by ST_Distance(centroids.geom,ST_MakeValid(st_transform('$epicentro_900913'::geometry,3857))) ASC limit 1";
            $resultGetSecondCity = query($connection, $stringaGetSecondCity);
            $rowGetSecondCity = $resultGetSecondCity->fetch(PDO::FETCH_ASSOC);
            $abitato_piu_vicino = pg_escape_string($rowGetSecondCity["abitato_piu_vicino"]);
            if (!$abitato_piu_vicino)
                $abitato_piu_vicino = "";
            $distanza_abitato_piu_vicino = $rowGetSecondCity["distanza_abitato_piu_vicino"];
            if (!$distanza_abitato_piu_vicino)
                $distanza_abitato_piu_vicino = Null;
            ///////////////

            $stringaUpdateInviluppo = "SELECT  (stats).sum as sum_pop
                FROM (SELECT ST_SummaryStats((ST_Clip(population.rast,'$buffer_geom_3857'::geometry))) As stats
                    FROM population
                                where ST_Intersects(ST_MakeValid('$buffer_geom_3857'::geometry),population.rast) 
                 ) As foo";

            $resultUpdateInviluppo = query($connection, $stringaUpdateInviluppo);
            $rowUpdateInviluppo = $resultUpdateInviluppo->fetch(PDO::FETCH_ASSOC);

            $sum_pop = $rowUpdateInviluppo["sum_pop"];
            if (!$sum_pop)
                $sum_pop = 0;

            //calcolo num_com
            $stringaUpdateInviluppoCom = "SELECT  count(name_3) as num_com from (
                SELECT  name_3, 0 as mioCampo
                                FROM gadm2
                                where ST_Intersects(ST_MakeValid(st_transform(st_setsrid('$buffer_geom_3857'::geometry,3857),4326)),gadm2.geom) 
                   ) as ciao    group by mioCampo
                 ";
            $resultUpdateInviluppoCom = query($connection, $stringaUpdateInviluppoCom);
            $rowUpdateInviluppoCom = $resultUpdateInviluppoCom->fetch(PDO::FETCH_ASSOC);
            $num_com = $rowUpdateInviluppoCom["num_com"];
            if (!$num_com)
                $num_com = 0;

            //calcolo affected_countries
            if (!$isFiji && !$isNZ) {
                $stringaUpdateInviluppoCountries = "SELECT array_agg(DISTINCT iso_3digit) as affected_countries
                                from world_bound 
                                where ST_Intersects(ST_MakeValid(st_transform(st_setsrid('$buffer_geom_3857'::geometry,3857),4326)),world_bound.geom) 
                 ";
                $resultUpdateInviluppoCountries = query($connection, $stringaUpdateInviluppoCountries);
                $rowUpdateInviluppoCountries = $resultUpdateInviluppoCountries->fetch(PDO::FETCH_ASSOC);
                $affected_countries = $rowUpdateInviluppoCountries["affected_countries"];
                if (!$affected_countries)
                    $affected_countries = '{}';
            }elseif ($isFiji) {
                $affected_countries = '{FJI}';
            } elseif ($isNZ) {
                $affected_countries = '{NZL}';
            }


            $stringaInsertEvento = "INSERT INTO eventi_terremoto(start_time, last_shake_time, max_magnitude, max_epicentro, 
       inviluppo_buffer_evento,popolazione_coinvolta,country,regione,citta_piu_vicina,distanza_citta_piu_vicina,placca_ricadente,placca_secondaria,distanza_faglia,is_sea,usgs_name_first,usgs_name_max, tsunami,n_shakes,typical_depth,pop_citta_piu_vicina,direzione_citta_piu_vicina,abitato_piu_vicino,distanza_abitato_piu_vicino,num_comuni,affected_countries,usgs_name_all ) values (TO_TIMESTAMP($time/1000), TO_TIMESTAMP($time/1000), $mag, "
                    . "'$epicentro_900913'::geometry, ST_Multi('$buffer_geom_900913'::geometry),$sum_pop,'$country','$regione','$citta_piu_vicina',$distanza_citta_piu_vicina,'$placca_ricadente','$placca_secondaria',$distanza_faglia, '$is_sea' ,'$usgs','$usgs',$tsunami,1,$depth,$pop_citta_piu_vicina,$direzione_citta_piu_vicina,'$abitato_piu_vicino',$distanza_abitato_piu_vicino,$num_com,'$affected_countries','{" . $usgs . "}' ) returning id_evento";
            $resultInsertEvento = query($connection, $stringaInsertEvento);
            $rowInsertEvento = $resultInsertEvento->fetch(PDO::FETCH_ASSOC);
            $id_evento = $rowInsertEvento["id_evento"];

            echo "Evento $id_evento creato <br> \n";
            $body = "Il terremoto $usgs - $placeusgs ha creato l'evento $id_evento. <br>"
                    . " Link usgs: <a href='https://earthquake.usgs.gov/earthquakes/eventpage/$usgs'>https://earthquake.usgs.gov/earthquakes/eventpage/$usgs</a> ;<br> affected countries: $affected_countries; ";

            email_sender("NEW SYSTEM: Creato evento $id_evento", $body);
        }
        $stringaUpdateTerremoti = "UPDATE world_earthquakes_1900_55 set is_lavorato = True, id_evento = $id_evento  WHERE ogc_fid =  $ogc_fid";
        $resultUpdateTerremoti = query($connection, $stringaUpdateTerremoti);

        //metti is_lavorato = true
    }
    echo "Elaborazioni terminate <br> \n";
}
