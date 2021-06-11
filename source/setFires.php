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

//error_reporting(0);
//ini_set('error_reporting', 0);
include_once __DIR__ . '/gestioneDb.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');

function delta_tempo($data_iniziale, $data_finale, $unita) {

    $data1 = strtotime($data_iniziale);
    $data2 = strtotime($data_finale);

    switch ($unita) {
        case "m": $unita = 1 / 60;
            break;  //MINUTI
        case "h": $unita = 1;
            break;  //ORE
        case "g": $unita = 24;
            break;  //GIORNI
        case "a": $unita = 8760;
            break;         //ANNI
    }

    $differenza = (($data2 - $data1) / 3600) / $unita;
    return $differenza;
}

$connection = connetti();
//modis

$stringaQuery = "SELECT
to_char(acq_date,'YYYY/MM/DD') as
acq_date
  FROM public.modis
  order by acq_date DESC
  limit 1";
$result = query($connection, $stringaQuery);
if ($result->rowCount() > 0) {
    $row = $result->fetch(PDO::FETCH_ASSOC);
    $last_day = $row["acq_date"];
    $today = date("Y/m/d");
    $delta_tempo = delta_tempo($last_day, $today, 'g');
    if ($delta_tempo < 1) {
        $link = "https://firms.modaps.eosdis.nasa.gov/active_fire/c6/shapes/zips/MODIS_C6_Global_24h.zip";
        $shp = "MODIS_C6_Global_24h.shp";
    } elseif ($delta_tempo < 2) {
        $link = "https://firms.modaps.eosdis.nasa.gov/active_fire/c6/shapes/zips/MODIS_C6_Global_48h.zip";
        $shp = "MODIS_C6_Global_48h.shp";
    } else {
        $link = "https://firms.modaps.eosdis.nasa.gov/active_fire/c6/shapes/zips/MODIS_C6_Global_7d.zip";
        $shp = "MODIS_C6_Global_7d.shp";
    }
} else {
    $link = "https://firms.modaps.eosdis.nasa.gov/active_fire/c6/shapes/zips/MODIS_C6_Global_7d.zip";
    $shp = "MODIS_C6_Global_7d.shp";
}
$now = time();
$dirUpload = "zipTemp/";
$dirDecompressa = $dirUpload . "decompresse/" . $now;
$query = $dirDecompressa . "/" . $shp;
$newfile = $dirUpload . $now . ".zip";
echo "Download file zip " . $link . " <br> \n";
if (copy($link, $newfile)) {

    $zip = new ZipArchive;
    $res = $zip->open($newfile);
    if ($res === TRUE) {
        mkdir($dirDecompressa);
        echo "Decompressione file zip " . " <br> \n";
        $estrai = $zip->extractTo($dirDecompressa);
        if ($estrai) {
            $res = $zip->close();
            unlink($newfile);
            $esegui = 'ogr2ogr -f PostgreSQL PG:"host=' . $host . ' user=' . $user . ' port=' . $port . ' dbname=' . $dbName . ' password=' . $password . '" "' . $query . '" -where "brightness>300 and confidence > 80" -skipfailures -append -nln modis';
            //echo $esegui;
            exec($esegui);
            echo "Caricamento Modis terminato <br> \n";
        }
    }
}


//viirs
$stringaQuery = "SELECT
to_char(acq_date,'YYYY/MM/DD') as
acq_date
  FROM public.viirs
  order by acq_date DESC
  limit 1";
$result = query($connection, $stringaQuery);
if ($result->rowCount() > 0) {
    $row = $result->fetch(PDO::FETCH_ASSOC);
    $last_day = $row["acq_date"];
    $today = date("Y/m/d");
    $delta_tempo = delta_tempo($last_day, $today, 'g');
    if ($delta_tempo < 1) {
        $link = "https://firms.modaps.eosdis.nasa.gov/active_fire/viirs/shapes/zips/VNP14IMGTDL_NRT_Global_24h.zip";
        $shp = "VNP14IMGTDL_NRT_Global_24h.shp";
    } elseif ($delta_tempo < 2) {
        $link = "https://firms.modaps.eosdis.nasa.gov/active_fire/viirs/shapes/zips/VNP14IMGTDL_NRT_Global_48h.zip";
        $shp = "VNP14IMGTDL_NRT_Global_48h.shp";
    } else {
        $link = "https://firms.modaps.eosdis.nasa.gov/active_fire/viirs/shapes/zips/VNP14IMGTDL_NRT_Global_7d.zip";
        $shp = "VNP14IMGTDL_NRT_Global_7d.shp";
    }
} else {
    $link = "https://firms.modaps.eosdis.nasa.gov/active_fire/viirs/shapes/zips/VNP14IMGTDL_NRT_Global_7d.zip";
    $shp = "VNP14IMGTDL_NRT_Global_7d.shp";
}
$now = time();
$dirUpload = "zipTemp/";
$dirDecompressa = $dirUpload . "decompresse/" . $now;
$query = $dirDecompressa . "/" . $shp;
$newfile = $dirUpload . $now . ".zip";
echo "Download file zip " . $link . " <br> \n";
if (copy($link, $newfile)) {

    $zip = new ZipArchive;
    $res = $zip->open($newfile);
    if ($res === TRUE) {
        mkdir($dirDecompressa);
        echo "Decompressione file zip " . " <br> \n";
        $estrai = $zip->extractTo($dirDecompressa);
        if ($estrai) {
            $res = $zip->close();
            unlink($newfile);
            $esegui = 'ogr2ogr -f PostgreSQL PG:"host=' . $host . ' user=' . $user . ' port=' . $port . ' dbname=' . $dbName . ' password=' . $password . '" "' . $query . '"  -where "bright_ti4>300 and confidence in (\'nominal\', \'high\', \'n\', \'h\')" -skipfailures -append -nln viirs';
            exec($esegui);
            echo "Caricamento viirs terminato <br> \n";
        }
    }
}

$numbersinsert = [];
$numberupdate = [];

$stringaQuery = ' SELECT  geom_3857,  st_transform(geom_3857,4326) AS geom_4326, giorno FROM(
  SELECT   ST_ChaikinSmoothing(st_Simplify(geom_3857,800),1 ) AS geom_3857, giorno FROM(
SELECT (st_dump(geom_3857)).geom as geom_3857 , giorno from (
    SELECT   st_union(geom_3857) as geom_3857, giorno from (
SELECT gid,
    (((((modis.acq_date || \' \'::text) || "substring"(modis.acq_time::text, 1, 2)) || \':\'::text) || "substring"(modis.acq_time::text, 3, 2)
  )::timestamp without time zone) as data, modis.acq_date as giorno,
       st_buffer(st_transform_null(geom,3857), 1500) as geom_3857,  is_lavorato, \'modis\' as sat
  FROM modis where is_lavorato is not True and confidence > 80   and acq_date > \'2000-01-01\'

  UNION

SELECT gid,
    (((((viirs.acq_date || \' \'::text) || "substring"(viirs.acq_time::text, 1, 2)) || \':\'::text) || "substring"(viirs.acq_time::text, 3, 2)
    )::timestamp without time zone) as data, viirs.acq_date as giorno,
       st_buffer(st_transform_null(geom,3857), 600) as geom_3857,  is_lavorato, \'viirs\' as sat
  FROM viirs  where is_lavorato is not True and confidence in (\'nominal\', \'high\', \'n\', \'h\')   and acq_date > \'2000-01-01\'
  ) as foo group by giorno order by giorno ASC
  )as foofoo) as foofoofoo where st_area(geom_3857) > (50000000) order by giorno ASC )
  as foofoofoofoo';
$result = query($connection, $stringaQuery);
echo "Inizio creazione eventi <br> \n";
if ($result->rowCount() > 0) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $geom_3857 = $row["geom_3857"];
        $geom_4326 = $row["geom_4326"];
        $giorno = $row["giorno"];
        $stringaQuery2 = "SELECT id_evento from eventi_fires "
                . " where st_dwithin(st_transform(inviluppo_evento,3857), '$geom_3857'::geometry,10000) and end_time + interval '1 day' >= '$giorno'  ";

        $result2 = query($connection, $stringaQuery2);
        if ($result2->rowCount() > 0) { //se esiste evento
            $row2 = $result2->fetch(PDO::FETCH_ASSOC);
            $id_evento = $row2["id_evento"];
            $new_end_time = $giorno;
            echo "Provo ad aggiorare evento $id_evento <br> \n";
            $stringaUpdate = "UPDATE eventi_fires SET end_time = '$new_end_time', "
                    . " inviluppo_evento  = ST_MakeValid(st_union(ST_MakeValid(inviluppo_evento),ST_MakeValid('$geom_4326')::geometry))"
                    . " WHERE id_evento = $id_evento returning inviluppo_evento";
            //echo "StringaUpdate $stringaUpdate <br> \n";
            $resultUpdate += $id_evento;
            $numberupdate = $resultUpdate->rowCount();
            $id_evento_sel = $id_evento;
            $row3 = $resultUpdate->fetch(PDO::FETCH_ASSOC);
            echo "Evento $id_evento aggiornato <br> \n";
            $inviluppo_evento_4326 = $row3["inviluppo_evento"];
            $stringaUpdateEvPred2 = "SELECT coalesce(name1,'') ||', '|| coalesce(name2,'') ||', '|| coalesce(name3,'') ||', '|| coalesce(name4,'') ||', '|| coalesce(name5,'') as citta_piu_vicina "
                    . " , degrees(ST_Azimuth(centroids.geom, st_centroid('$geom_3857'::geometry))) as direzione_citta_piu_vicina, "
                    . " ST_Distance(centroids.geom, '$geom_3857'::geometry) as distanza_citta_piu_vicina, centroids.p10a as pop_citta_piu_vicina
                                from centroids where centroids.p10a > 100000 order by ST_Distance(centroids.geom,'$geom_3857'::geometry) ASC limit 1";
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
                         ST_Distance(centroids.geom, '$geom_3857'::geometry) as distanza_abitato_piu_vicino
                                from centroids order by ST_Distance(centroids.geom, '$geom_3857'::geometry) ASC limit 1";
            $resultGetSecondCity = query($connection, $stringaGetSecondCity);
            $rowGetSecondCity = $resultGetSecondCity->fetch(PDO::FETCH_ASSOC);
            $abitato_piu_vicino = pg_escape_string($rowGetSecondCity["abitato_piu_vicino"]);
            if (!$abitato_piu_vicino)
                $abitato_piu_vicino = "";
            $distanza_abitato_piu_vicino = $rowGetSecondCity["distanza_abitato_piu_vicino"];
            if (!$distanza_abitato_piu_vicino)
                $distanza_abitato_piu_vicino = Null;

            $stringaUpdateInviluppoCountries = "SELECT array_agg(DISTINCT iso_3digit) as affected_countries
                                from world_bound
                                where ST_Intersects(ST_MakeValid('$inviluppo_evento_4326'::geometry),world_bound.geom)
                 ";
            $resultUpdateInviluppoCountries = query($connection, $stringaUpdateInviluppoCountries);
            $rowUpdateInviluppoCountries = $resultUpdateInviluppoCountries->fetch(PDO::FETCH_ASSOC);
            $affected_countries = $rowUpdateInviluppoCountries["affected_countries"];
            if (!$affected_countries)
                $affected_countries = '{}';
            ///////////////
            $stringaUpdateAll = "UPDATE eventi_fires SET citta_piu_vicina = '$citta_piu_vicina',"
                    . " distanza_citta_piu_vicina = $distanza_citta_piu_vicina,"
                    . " pop_citta_piu_vicina = $pop_citta_piu_vicina,"
                    . " direzione_citta_piu_vicina = $direzione_citta_piu_vicina,"
                    . " abitato_piu_vicino = '$abitato_piu_vicino',"
                    . " distanza_abitato_piu_vicino = $distanza_abitato_piu_vicino, "
                    . " affected_countries = '$affected_countries'"
                    . " WHERE id_evento = $id_evento_sel";
            $resultUpdateAll = query($connection, $stringaUpdateAll);
        } else { //se non esiste evento
            $stringaInsertEvento = "INSERT INTO public.eventi_fires( start_time, end_time, inviluppo_evento)
            VALUES ('$giorno','$giorno','$geom_4326') returning id_evento, inviluppo_evento, st_transform(inviluppo_evento,3857) as geom_3857;";
            $resultInsertEvento = query($connection, $stringaInsertEvento);
            //echo $stringaInsertEvento;
            $numbersinsert += $id_evento;
            $rowInsertEvento = $resultInsertEvento->fetch(PDO::FETCH_ASSOC);
            $id_evento = $rowInsertEvento["id_evento"];
            $inviluppo_evento_4326 = $rowInsertEvento["inviluppo_evento"];
            $geom_3857 = $rowInsertEvento["geom_3857"];
            echo "Evento $id_evento creato <br> \n";


            $stringaUpdateEvPred2 = "SELECT coalesce(name1,'') ||', '|| coalesce(name2,'') ||', '|| coalesce(name3,'') ||', '|| coalesce(name4,'') ||', '|| coalesce(name5,'') as citta_piu_vicina "
                    . " , degrees(ST_Azimuth(centroids.geom, st_centroid('$geom_3857'::geometry))) as direzione_citta_piu_vicina, "
                    . " ST_Distance(centroids.geom, '$geom_3857'::geometry) as distanza_citta_piu_vicina, centroids.p10a as pop_citta_piu_vicina
                                from centroids where centroids.p10a > 100000 order by ST_Distance(centroids.geom,'$geom_3857'::geometry) ASC limit 1";
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
                         ST_Distance(centroids.geom, '$geom_3857'::geometry) as distanza_abitato_piu_vicino
                                from centroids order by ST_Distance(centroids.geom, '$geom_3857'::geometry) ASC limit 1";
            $resultGetSecondCity = query($connection, $stringaGetSecondCity);
            $rowGetSecondCity = $resultGetSecondCity->fetch(PDO::FETCH_ASSOC);
            $abitato_piu_vicino = pg_escape_string($rowGetSecondCity["abitato_piu_vicino"]);
            if (!$abitato_piu_vicino)
                $abitato_piu_vicino = "";
            $distanza_abitato_piu_vicino = $rowGetSecondCity["distanza_abitato_piu_vicino"];
            if (!$distanza_abitato_piu_vicino)
                $distanza_abitato_piu_vicino = Null;

            $stringaUpdateInviluppoCountries = "SELECT array_agg(DISTINCT iso_3digit) as affected_countries
                                from world_bound
                                where ST_Intersects(ST_MakeValid('$inviluppo_evento_4326'::geometry),world_bound.geom)
                 ";
            $resultUpdateInviluppoCountries = query($connection, $stringaUpdateInviluppoCountries);
            $rowUpdateInviluppoCountries = $resultUpdateInviluppoCountries->fetch(PDO::FETCH_ASSOC);
            $affected_countries = $rowUpdateInviluppoCountries["affected_countries"];
            if (!$affected_countries)
                $affected_countries = '{}';
            ///////////////
            $stringaUpdateAll = "UPDATE eventi_fires SET citta_piu_vicina = '$citta_piu_vicina',"
                    . " distanza_citta_piu_vicina = $distanza_citta_piu_vicina,"
                    . " pop_citta_piu_vicina = $pop_citta_piu_vicina,"
                    . " direzione_citta_piu_vicina = $direzione_citta_piu_vicina,"
                    . " abitato_piu_vicino = '$abitato_piu_vicino',"
                    . " distanza_abitato_piu_vicino = $distanza_abitato_piu_vicino, "
                    . " affected_countries = '$affected_countries'"
                    . " WHERE id_evento = $id_evento";
            $resultUpdateAll = query($connection, $stringaUpdateAll);
        }

    }
}

$update = "UPDATE viirs set is_lavorato = True WHERE is_lavorato is not True ";
$connection->query($update);
$update2 = "UPDATE modis set is_lavorato = True WHERE is_lavorato is not True ";
$connection->query($update2);
$numbersinsert = count($numbersinsert);
$numberupdate = count($numberupdate);
email_sender("NEW SYSTEM: Creati eventi fires", "Creati $numbersinsert nuovi eventi fires, aggiornati $numberupdate  eventi .");
echo "Fine creazione eventi <br> \n";
