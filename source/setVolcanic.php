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

ini_set('error_reporting', 1);
error_reporting(E_ALL);
include_once __DIR__ . '/emailSender.php';

include_once __DIR__ . '/gestioneDb.php';
$oggi = time();
$preurl = 'https://www.gdacs.org';
$link = "https://www.gdacs.org/datareport/resources/VO/";
$sec = 0;
$ch = curl_init();
$formatdate = "D, d M Y H:i:s \G\M\T";

curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_URL, $link);
$result = curl_exec($ch);
curl_close($ch);
preg_match_all('/(HREF)=("[^"]*")/', $result, $allHREF);

foreach ($allHREF[2] as $link) { // ITERAZIONE PER OGNI VENTO
    try {
        if (strpos($link, 'VO') !== false) {
            $urlevento = $preurl . str_replace('"', '', $link);
            $chevento = curl_init();
            curl_setopt($chevento, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($chevento, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chevento, CURLOPT_URL, $urlevento);
            $result2 = curl_exec($chevento);
            curl_close($chevento);
            preg_match_all('/(HREF)=("[^"]*")/', $result2, $allHREFevento);

// GET ALL THE ID_EVENTO
            $listepisode = [];

            foreach ($allHREFevento[2] as $link2) {
                $urllistaeventi = $preurl . str_replace('"', '', $link2);
                $end = explode('_', $link2);
                $evento = end($end);
                $id_episodio = explode('.', $evento)[0];
                $estensione = str_replace('"', '', explode('.', $evento)[1]);
                if ($estensione == "geojson") {
                    array_push($listepisode, $id_episodio);
                }
            }

            //LINK2 = "/datareport/resources/TC/1000128/Shape_1000128_6.zip"

            $last_episodio = max($listepisode);

            // INSERIRE CONTROLLO NEL DB


            $id_gdacs = $end[1];

            //  if ($id_gdacs > 1000460) {
            $connection = connetti(); //VERIFICO  SE PRESENTE EVENTO
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
            $stringaQuery = "SELECT * FROM public.volcanic WHERE gdacs_id = '$id_gdacs'";
            $resultselect = query($connection, $stringaQuery);
            $crisis_event_episode = "";
            print "\n";
            print '++++++++++ EPISODIO '.$id_gdacs.' ++++++++++++++';
            print "\n";

            if ($resultselect->rowCount() == 0) { // EVENTO NUOVO
                $row = $resultselect->fetch(PDO::FETCH_ASSOC);
                $crisis_event_episode = $row["crisis_event_episode"];
                $linkgeojson = "/datareport/resources/VO/" . $end[1] . "/geojson_" . $end[1] . "_" . $last_episodio . ".geojson";
                $urllistaeventi = $preurl . str_replace('"', '', $linkgeojson);
                print "\n";
                print '$urllistaeventi ';
                print "\n";
                print $urllistaeventi;
                print "\n";
                $chepisodio = curl_init();
                curl_setopt($chepisodio, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($chepisodio, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($chepisodio, CURLOPT_URL, $urllistaeventi);
                $urllistaeventi = curl_exec($chepisodio);
                curl_close($chepisodio);
                $obj = json_decode($urllistaeventi, true);

                $xml = getxml($preurl, "VO", ".xml", $end[1], $last_episodio, "/rss_");


                //$xml = simplexml_load_file($urllistaeventi) or die("Error: Cannot create object xml");
                if ($xml) {
                    if ($xml->channel) {
                        $tdate = $xml->channel->item->todate;
                        $gdacs_id = $xml->channel->item->eventid;
                        $created_at = $xml->channel->item->pubDate;
                        $crisis_alertLevel = $xml->channel->item->alertlevel;
                        $event_episode = $xml->channel->item->episodeid;
                        $crisis_eventname = $xml->channel->item->eventname;
                        $crisis_population = $xml->channel->item->population->attributes()['value'];
                        $crisis_severity = $xml->channel->item->severity;
                        $crisis_severity_h = json_encode($xml->channel->item->severity->attributes());
                        $json = json_decode($crisis_severity_h, true);
                        $crisis_severity_hash = json_encode($json['@attributes']);
                        $crisis_vulnerability = $xml->channel->item->vulnerability->attributes()['value'];
                        $crisis_vulnerability_h = json_encode($xml->channel->item->vulnerability);
                        $json = json_decode($crisis_vulnerability_h, true);
                        $crisis_vulnerability_hash = json_encode($json['@attributes']);
                        $dc_date = $xml->channel->item->fromdate;
                        $dc_description = $xml->channel->item->description;
                        $dc_title = $xml->channel->item->title;
                        $gn_parentCountry = json_encode($xml->channel->item->country);
                        $crisis_point = $xml->channel->item->point;
                        $y = explode(" ", $crisis_point)[0];
                        $x = explode(" ", $crisis_point)[1];

                        $date = DateTime::createFromFormat($formatdate, $tdate);
                        $date = $date->format('Y-m-d');
                        $todate = strtotime($date);

                        $sql = "INSERT INTO public.volcanic(
                 gdacs_id, created_at, crisis_alert_level, crisis_event_episode, crisis_population, crisis_severity, crisis_severity_hash, crisis_vulnerability, crisis_vulnerability_hash, dc_date, dc_description, dc_title, gn_parent_country, geom)
                  VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, st_setsrid(ST_MakePoint(?,?),4326));";
                        $params = array(
                            $gdacs_id, $created_at, $crisis_alertLevel, $event_episode, $crisis_population, $crisis_severity, $crisis_severity_hash,
                            $crisis_vulnerability, $crisis_vulnerability_hash, $dc_date, $dc_description, $dc_title, $gn_parentCountry, $x, $y
                        );
                    } elseif ($xml->info) {

                        $firstinfos = $xml->info->children();
                        $dc_description = $xml->info->description;
                        $dc_title = $xml->info->headline;
                        $geometry = (string) $xml->info->area->polygon[0]->asXML();
                        //incidents
                        foreach ($firstinfos as $firstinfo) {
                            $get_var = $firstinfo->valueName;
                            if ($get_var == "todate") {
                                $variabile = $firstinfo->value;
                                $key = "tdate";
                            } elseif ($get_var == "eventid") {
                                $variabile = $firstinfo->value;
                                $key = "gdacs_id";
                            } elseif ($get_var == "currentepisodeid") {
                                $variabile = $firstinfo->value;
                                $key = "event_episode";
                            } elseif ($get_var == "alertlevel") {
                                $variabile = $firstinfo->value;
                                $key = "crisis_alertLevel";
                            } elseif ($get_var == "severity") {
                                $variabile = $firstinfo->value;
                                $key = "crisis_severity";
                            } elseif ($get_var == "population") {
                                $variabile = explode(" ", $firstinfo->value)[0];
                                $key = "crisis_population";
                            } elseif ($get_var == "vulnerability") {
                                $variabile = $firstinfo->value;
                                $key = "crisis_vulnerability";
                            } elseif ($get_var == "fromdate") {
                                $variabile = $firstinfo->value;
                                $key = "dc_date";
                            } elseif ($get_var == "country") {
                                $variabile = $firstinfo->value;
                                $key = "gn_parentCountry";
                            } elseif ($get_var == "datemodified") {
                                $variabile = $firstinfo->value;
                                $key = "created_at";
                            }

                            if ($key) {
                                $parametri[$key] = (string) $variabile[0];
                            }
                        }
                        $GEOM = str_replace(" ", "!", $geometry);
                        $GEOM = str_replace(",", " ", $GEOM);
                        $geometry = str_replace("!", ",", $GEOM);

                        $GEO = 'LINESTRING (' . $geometry . ')';
                        $gdacs_id = $parametri['gdacs_id'];
                        $tdate = $parametri['tdate'];
                        $created_at = $parametri['created_at'];
                        $crisis_alert_level = $parametri['crisis_alert_level'];
                        $event_episode = $parametri['event_episode'];
                        $crisis_population = $parametri['crisis_population'];
                        $crisis_severity = $parametri['crisis_severity'];
                        $crisis_vulnerability = $parametri['crisis_vulnerability'];
                        $dc_date = $parametri['dc_date'];
                        $gn_parentCountry = $parametri['gn_parentCountry'];

                        $date = DateTime::createFromFormat($formatdate, $tdate);
                        $date = $date->format('Y-m-d');
                        $todate = strtotime($date);

                        $sql = "INSERT INTO public.volcanic( gdacs_id, created_at, crisis_alert_level, crisis_event_episode, crisis_population, crisis_severity,  crisis_vulnerability, dc_date, dc_description, dc_title, gn_parent_country, geom)
                VALUES ( ? , ?, ? ,  ?,  ?,  ?,  ?,  ?,  ?,  ?,  ?,  ST_Centroid(st_setsrid(ST_FlipCoordinates(ST_MakePolygon(ST_GeomFromText(?))),4326)))";
                        $params = array(
                            $gdacs_id, $created_at, $crisis_alert_level, $event_episode, $crisis_population, $crisis_severity, $crisis_vulnerability,
                            $dc_date, $dc_description, $dc_title, $gn_parentCountry, $GEO);
                    }

                    print "\n";
                    print_r('$date -> ' . $todate . ' ON EVENTO_EPISODE : ' . $end[1] . "_" . $last_episodio.' rowCount() == 0' );
                    print "\n";
                    //$todate = strtotime(str_replace("/", " ", $date));
                    $controldate = strtotime('2018-01-1');
                    if ($todate >= $controldate) {

                        if ($crisis_alertLevel != "Green") {
                            // code...
                            print "\n";
                            print_r('ENTRATO NON E STATO TROVATO NESSUN EVENTO -> ');
                            print "\n";



                            $connection = connetti();
                            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
                            $stmt = $connection->prepare($sql);

                            if ($stmt->execute($params)) {
                                echo "INSERITO IN volcanic $gdacs_id WITH EVENT EPISODE : $event_episode <br>";
                                  email_sender("NEW SYSTEM: Creato evento volcanic", "Creato evento volcanic $gdacs_id .");
                                //update
                                $stringaUpdateEvPredCountry = "SELECT iso_3digit as country,is_sea, sea
                                                    from world_bound
                                                    where ST_Intersects(st_setsrid(ST_MakePoint(?,?),4326),world_bound.geom) order by is_sea limit 1";
                                $stmt0 = $connection->prepare($stringaUpdateEvPredCountry);
                                $stmt0->execute(array($x, $y));
                                //echo $stringaUpdateEvPred;
                                $rowUpdateEvPredCountry = $stmt0->fetch(PDO::FETCH_ASSOC);
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
                                                    where ST_Intersects(ST_MakeValid(st_transform(st_setsrid(ST_MakePoint(?,?),4326),3857)),regions.geom) limit 1";
                                $stmt2 = $connection->prepare($stringaUpdateEvPred);
                                $stmt2->execute(array($x, $y));
                                $rowUpdateEvPred = $stmt2->fetch(PDO::FETCH_ASSOC);

                                $regione = pg_escape_string($rowUpdateEvPred["regione"]);
                                if (!$regione)
                                    $regione = $sea;
                                $stringaUpdateEvPred2 = "SELECT coalesce(name1,'') ||', '|| coalesce(name2,'') ||', '|| coalesce(name3,'') ||', '|| coalesce(name4,'') ||', '|| coalesce(name5,'') as citta_piu_vicina , degrees(ST_Azimuth(centroids.geom, ST_transform(st_setsrid(ST_MakePoint(?,?),4326),3857))) as direzione_citta_piu_vicina, "
                                        . " ST_Distance(centroids.geom, st_transform(st_setsrid(ST_MakePoint(?,?),4326),3857)) as distanza_citta_piu_vicina, centroids.p10a as pop_citta_piu_vicina
                                                    from centroids where centroids.p10a > 100000 order by ST_Distance(centroids.geom,ST_transform(st_setsrid(ST_MakePoint(?,?),4326),3857)) ASC limit 1";
                                $stmt3 = $connection->prepare($stringaUpdateEvPred2);
                                $stmt3->execute(array($x, $y, $x, $y, $x, $y));
                                $rowUpdateEvPred2 = $stmt3->fetch(PDO::FETCH_ASSOC);
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
                                             ST_Distance(centroids.geom, st_transform(st_setsrid(ST_MakePoint(?,?),4326),3857)) as distanza_abitato_piu_vicino
                                                    from centroids order by ST_Distance(centroids.geom,ST_transform(st_setsrid(ST_MakePoint(?,?),4326),3857)) ASC limit 1";
                                $stmt4 = $connection->prepare($stringaGetSecondCity);
                                $stmt4->execute(array($x, $y, $x, $y));
                                $rowGetSecondCity = $stmt4->fetch(PDO::FETCH_ASSOC);
                                $abitato_piu_vicino = pg_escape_string($rowGetSecondCity["abitato_piu_vicino"]);
                                if (!$abitato_piu_vicino)
                                    $abitato_piu_vicino = "";
                                $distanza_abitato_piu_vicino = $rowGetSecondCity["distanza_abitato_piu_vicino"];
                                if (!$distanza_abitato_piu_vicino)
                                    $distanza_abitato_piu_vicino = Null;

                                $updateSql = 'UPDATE public.volcanic
                                SET regione=?, country=?, citta_piu_vicina=?, direzione_citta_piu_vicina=?, abitato_piu_vicino=?,
                            distanza_abitato_piu_vicino=?, is_sea=?, pop_citta_piu_vicina=?, distanza_citta_piu_vicina = ?
                            where gdacs_id = ?';
                                $stmt5 = $connection->prepare($updateSql);
                                print "\n";
                                print " NUM 0";
                                print "\n";
                                $stmt5->execute(
                                        array(
                                            $regione, $country, $citta_piu_vicina, $direzione_citta_piu_vicina, $abitato_piu_vicino, $distanza_abitato_piu_vicino,
                                            $is_sea, $pop_citta_piu_vicina, $distanza_citta_piu_vicina, $gdacs_id
                                        )
                                );
                                print "\n";
                                print " NUM 1";
                                print "\n";

                                $features = $obj['features'];
                                $alertlevelfeature = "";

                                foreach ($features as $feature) {
                                    $properties = $feature['properties'];
                                    print "\n";
                                    print " NUM 2";
                                    print "\n";
                                    //   TROVATO EVENTO ORANGE
                                    $geometry = $feature['geometry'];

                                    if ($geometry['type'] == "Polygon" || $geometry['type'] == "MultiPolygon") {
                                        print "\n";
                                        print " NUM 3 polygon";
                                        print "\n";
                                        $geometry = json_encode($feature['geometry'], JSON_PRETTY_PRINT);
                                        $polygontype = $properties['Class'];
                                        //$features = $obj['features'];
                                        $geominser_query = "INSERT INTO public.volcanic_area(
                                                      gdacs_id, crisis_event_episode, polygontype, geom)
                                                    	VALUES (?, ?, ?, st_setsrid(ST_GeomFromGeoJSON(?),4326))";
                                        $stmt = $connection->prepare($geominser_query);
                                        $params = array($gdacs_id, $event_episode, $polygontype, $geometry);
                                        print "\n";
                                        print " NUM 4";
                                        print "\n";
                                        if ($stmt->execute($params)) {
                                            print "\n";
                                            print_r($stmt->errorInfo());
                                            print "\n";
                                            print_r('NEW polygon inserted into volcanic_area ' . " --> " . $gdacs_id . " with alert level -> " . $polygontype);
                                            print "\n";
                                        } else {

                                            print "\n";
                                            print_r(' --> IMPOSSIBILE INSERIRE CICLONI IN  FLOODS AREA ' . $gdacs_id . " with alert level -> " . $polygontype);
                                            print "\n";
                                            print_r($stmt->errorInfo());
                                            print "\n";
                                        }
                                    }// eliminare QUESTO
                                }
                            } else {
                                print "\n";
                                print 'ERRORE INSERIMENTO EVENTO ';
                                print "\n";
                                print$stmt->errorInfo();
                                print "\n";
                            }
                        }else {
                          print "\n";
                          print '**************EVENTO GREEN PASSO PROSSIMO EVENTO **************';
                          print "\n";
                        }
                    } else {
                        print "\n";
                        print 'CONTROLLO DATA NON PASSATO';
                        print "\n";
                    }
                } else {
                    print "\n";
                    print 'IMPOSSIBILE CARICARE FIL XML';
                    print "\n";
                }
            } elseif ($resultselect->rowCount() == 1) {

                print "\n";
                print '---->>>> EVENTO GIA INSERITO PROVO AD AGGIORNARE';
                print "\n";


                $crisis_event_episode = "0";
                $row = $resultselect->fetch(PDO::FETCH_ASSOC);
                $crisis_event_episode = $row["crisis_event_episode"];
                $linkgeojson = "/datareport/resources/VO/" . $end[1] . "/geojson_" . $end[1] . "_" . $last_episodio . ".geojson";
                $urllistaeventi = $preurl . str_replace('"', '', $linkgeojson);
                $chepisodio = curl_init();
                curl_setopt($chepisodio, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($chepisodio, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($chepisodio, CURLOPT_URL, $urllistaeventi);
                $urllistaeventi = curl_exec($chepisodio);
              /*  print "\n";
                print '$urllistaeventi ';
                print "\n";
                print $urllistaeventi;
                print "\n";*/
                curl_close($chepisodio);
                $obj = json_decode($urllistaeventi, true);
                $xml = getxml($preurl, "VO", ".xml", $end[1], $last_episodio, "/rss_");


                //$xml = simplexml_load_file($urllistaeventi) or die("Error: Cannot create object xml");
                if ($xml) {
                    if ($xml->channel) {
                        $tdate = $xml->channel->item->todate;
                        $gdacs_id = $xml->channel->item->eventid;
                        $created_at = $xml->channel->item->pubDate;
                        $crisis_alertLevel = $xml->channel->item->alertlevel;
                        $event_episode = $xml->channel->item->episodeid;
                        $crisis_eventname = $xml->channel->item->eventname;
                        $crisis_population = $xml->channel->item->population->attributes()['value'];
                        $crisis_severity = $xml->channel->item->severity;
                        $crisis_severity_h = json_encode($xml->channel->item->severity->attributes());
                        $json = json_decode($crisis_severity_h, true);
                        $crisis_severity_hash = json_encode($json['@attributes']);
                        $crisis_vulnerability = $xml->channel->item->vulnerability->attributes()['value'];
                        $crisis_vulnerability_h = json_encode($xml->channel->item->vulnerability);
                        $json = json_decode($crisis_vulnerability_h, true);
                        $crisis_vulnerability_hash = json_encode($json['@attributes']);
                        $dc_date = $xml->channel->item->fromdate;
                        $dc_description = $xml->channel->item->description;
                        $dc_title = $xml->channel->item->title;
                        $gn_parentCountry = json_encode($xml->channel->item->country);
                        $crisis_point = $xml->channel->item->point;
                        $y = explode(" ", $crisis_point)[0];
                        $x = explode(" ", $crisis_point)[1];
                        $date = DateTime::createFromFormat($formatdate, $tdate);
                        $date = $date->format('Y-m-d');
                        $todate = strtotime($date);

                        $sqlupdate_test = "UPDATE public.volcanic
                      	SET created_at=?, crisis_alert_level=?, crisis_event_episode=?, crisis_population=?, crisis_severity=?, crisis_severity_hash=?,
                        crisis_vulnerability=?, crisis_vulnerability_hash=?, dc_date=?, dc_description=?, dc_title=?, gn_parent_country=?, geom= st_setsrid(ST_MakePoint(?,?),4326),
                      	WHERE gdacs_id= ;";

                                $params = array(
                                     $created_at, $crisis_alertLevel, $event_episode, $crisis_population, $crisis_severity, $crisis_severity_hash,
                                    $crisis_vulnerability, $crisis_vulnerability_hash, $dc_date, $dc_description, $dc_title, $gn_parentCountry, $x, $y
                                );

                    } elseif ($xml->info) {

                        $firstinfos = $xml->info->children();
                        $dc_description = $xml->info->description;
                        $dc_title = $xml->info->headline;
                        $geometry = (string) $xml->info->area->polygon[0]->asXML();
                        //incidents
                        foreach ($firstinfos as $firstinfo) {
                          $get_var = $firstinfo->valueName;
                          if ($get_var == "todate") {
                                $variabile = $firstinfo->value;
                                $key = "tdate";
                            } elseif ($get_var == "eventid") {
                                $variabile = $firstinfo->value;
                                $key = "gdacs_id";
                            } elseif ($get_var == "currentepisodeid") {
                                $variabile = $firstinfo->value;
                                $key = "event_episode";
                            } elseif ($get_var == "alertlevel") {
                                $variabile = $firstinfo->value;
                                $key = "crisis_alertLevel";
                            } elseif ($get_var == "severity") {
                                $variabile = $firstinfo->value;
                                $key = "crisis_severity";
                            } elseif ($get_var == "population") {
                                $variabile = explode(" ", $firstinfo->value)[0];
                                $key = "crisis_population";
                            } elseif ($get_var == "vulnerability") {
                                $variabile = $firstinfo->value;
                                $key = "crisis_vulnerability";
                            } elseif ($get_var == "fromdate") {
                                $variabile = $firstinfo->value;
                                $key = "dc_date";
                            } elseif ($get_var == "country") {
                                $variabile = $firstinfo->value;
                                $key = "gn_parentCountry";
                            } elseif ($get_var == "datemodified") {
                                $variabile = $firstinfo->value;
                                $key = "created_at";
                            }

                            if ($key) {
                                $parametri[$key] = (string) $variabile[0];
                            }
                        }
                        $GEOM = str_replace(" ", "!", $geometry);
                        $GEOM = str_replace(",", " ", $GEOM);
                        $geometry = str_replace("!", ",", $GEOM);

                        $GEO = 'LINESTRING (' . $geometry . ')';
                        $gdacs_id = $parametri['gdacs_id'];
                        $tdate = $parametri['tdate'];
                        $created_at = $parametri['created_at'];
                        $crisis_alert_level = $parametri['crisis_alert_level'];
                        $event_episode = $parametri['event_episode'];
                        $crisis_population = $parametri['crisis_population'];
                        $crisis_severity = $parametri['crisis_severity'];
                        $crisis_vulnerability = $parametri['crisis_vulnerability'];
                        $dc_date = $parametri['dc_date'];
                        $gn_parentCountry = $parametri['gn_parentCountry'];

                        $date = DateTime::createFromFormat($formatdate, $tdate);
                        $date = $date->format('Y-m-d');
                        $todate = strtotime($date);
                        $sqlupdate_test = "UPDATE public.volcanic
                        SET created_at=?, crisis_alert_level=?, crisis_event_episode=?, crisis_population=?, crisis_severity=?,
                        crisis_vulnerability=?, dc_date=?, dc_description=?, dc_title=?, gn_parent_country=?, geom= ST_Centroid(st_setsrid(ST_FlipCoordinates(ST_MakePolygon(ST_GeomFromText(?))),4326))
                        WHERE gdacs_id= ;";
                      $params = array(
                            $created_at, $crisis_alert_level, $event_episode, $crisis_population, $crisis_severity, $crisis_vulnerability,
                            $dc_date, $dc_description, $dc_title, $gn_parentCountry, $GEO, $gdacs_id);
                    }



                    print "\n";
                    print_r('$date -> ' . $todate . ' ON EVENTO_EPISODE : ' . $end[1] . "_" . $last_episodio.' rowCount() == 1' );
                    print "\n";
                    $controldate = strtotime('2018-01-1');
                    if ($todate >= $controldate) {
                        print "\n";
                        print 'CONTROLLO DATA PASSATO PER EVENTO :';
                        print "\n";
                        //print_r($metadata['eventid'] . "_" . $metadata['episodeid']);
                        print "\n";
                        print_r('$todate : ' . $todate . ' $controldate : ' . $controldate);
                        print "\n";
                        if ($crisis_alertLevel != "Green") {
                            // code...
                            print "\n";
                            print_r('ENTRATO  IN UPDATE NON E STATO TROVATO NESSUN EVENTO -> ');
                            print "\n";

                            if ($last_episodio > $crisis_event_episode) {

                              $stmt00 = $connection->prepare($sqlupdate_test);

                              if ($stmt00->execute($params)) {
                                  print "\n";
                                  print_r(' --> AGGIORNATO VOLCANIC  TEST ' . $gdacs_id);
                                  print "\n";
                                  email_sender("NEW SYSTEM: Aggiornato evento volcanic", "Aggiornato evento volcanic $gdacs_id .");
                              } else {
                                  print "\n";
                                  print "\n";
                                  print_r(' --> IMPOSSIBILE AGGIORNARE VOLCANIC  TEST ' . $gdacs_id);
                                  print "\n";
                              }

                                //echo "inserito $crisis_eventid <br>";
                                //email_sender("NEW SYSTEM: Creato evento floods", "Creato evento floods $crisis_eventid .");
                                //update
                                $stringaUpdateEvPredCountry = "SELECT iso_3digit as country,is_sea, sea
                                                                        from world_bound
                                                                        where ST_Intersects(st_setsrid(ST_MakePoint(?,?),4326),world_bound.geom) order by is_sea limit 1";
                                $stmt0 = $connection->prepare($stringaUpdateEvPredCountry);
                                $stmt0->execute(array($x, $y));
                                //echo $stringaUpdateEvPred;
                                $rowUpdateEvPredCountry = $stmt0->fetch(PDO::FETCH_ASSOC);
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
                                                                        where ST_Intersects(ST_MakeValid(st_transform(st_setsrid(ST_MakePoint(?,?),4326),3857)),regions.geom) limit 1";
                                $stmt2 = $connection->prepare($stringaUpdateEvPred);
                                $stmt2->execute(array($x, $y));
                                $rowUpdateEvPred = $stmt2->fetch(PDO::FETCH_ASSOC);

                                $regione = pg_escape_string($rowUpdateEvPred["regione"]);
                                if (!$regione)
                                    $regione = $sea;
                                $stringaUpdateEvPred2 = "SELECT coalesce(name1,'') ||', '|| coalesce(name2,'') ||', '|| coalesce(name3,'') ||', '|| coalesce(name4,'') ||', '|| coalesce(name5,'') as citta_piu_vicina , degrees(ST_Azimuth(centroids.geom, ST_transform(st_setsrid(ST_MakePoint(?,?),4326),3857))) as direzione_citta_piu_vicina, "
                                        . " ST_Distance(centroids.geom, st_transform(st_setsrid(ST_MakePoint(?,?),4326),3857)) as distanza_citta_piu_vicina, centroids.p10a as pop_citta_piu_vicina
                                                                        from centroids where centroids.p10a > 100000 order by ST_Distance(centroids.geom,ST_transform(st_setsrid(ST_MakePoint(?,?),4326),3857)) ASC limit 1";
                                $stmt3 = $connection->prepare($stringaUpdateEvPred2);
                                $stmt3->execute(array($x, $y, $x, $y, $x, $y));
                                $rowUpdateEvPred2 = $stmt3->fetch(PDO::FETCH_ASSOC);
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
                                                                 ST_Distance(centroids.geom, st_transform(st_setsrid(ST_MakePoint(?,?),4326),3857)) as distanza_abitato_piu_vicino
                                                                        from centroids order by ST_Distance(centroids.geom,ST_transform(st_setsrid(ST_MakePoint(?,?),4326),3857)) ASC limit 1";
                                $stmt4 = $connection->prepare($stringaGetSecondCity);
                                $stmt4->execute(array($x, $y, $x, $y));
                                $rowGetSecondCity = $stmt4->fetch(PDO::FETCH_ASSOC);
                                $abitato_piu_vicino = pg_escape_string($rowGetSecondCity["abitato_piu_vicino"]);
                                if (!$abitato_piu_vicino)
                                    $abitato_piu_vicino = "";
                                $distanza_abitato_piu_vicino = $rowGetSecondCity["distanza_abitato_piu_vicino"];
                                if (!$distanza_abitato_piu_vicino)
                                    $distanza_abitato_piu_vicino = Null;

                                $updateSql = 'UPDATE public.volcanic
                                                    SET regione=?, country=?, citta_piu_vicina=?, direzione_citta_piu_vicina=?, abitato_piu_vicino=?,
                                                distanza_abitato_piu_vicino=?, is_sea=?, pop_citta_piu_vicina=?, distanza_citta_piu_vicina = ?
                                                where gdacs_id = ?';
                                $stmt5 = $connection->prepare($updateSql);
                                $stmt5->execute(
                                        array(
                                            $regione, $country, $citta_piu_vicina, $direzione_citta_piu_vicina, $abitato_piu_vicino, $distanza_abitato_piu_vicino,
                                            $is_sea, $pop_citta_piu_vicina, $distanza_citta_piu_vicina, $gdacs_id
                                        )
                                );

                                $stringadelete = "DELETE FROM public.volcanic_area
                              	WHERE gdacs_id = ?";

                                $stmtdelete = $connection->prepare($stringadelete);
                                if ($stmtdelete->execute(array($gdacs_id))) {
                                    print "\n";
                                    print_r(' --> CANCELLATI EVENTI IN  FLOODS AREA');
                                    print "\n";
                                    $features = $obj['features'];

                                    foreach ($features as $feature) {

                                        $properties = $feature['properties'];
                                        $alertlevel = $properties['alertlevel'];
                                        $geometry = $feature['geometry'];
                                        if ($geometry['type'] == "Polygon" || $geometry['type'] == "MultiPolygon") {
                                            $geometry = json_encode($feature['geometry'], JSON_PRETTY_PRINT);
                                            $polygontype = $properties['Class'];

                                            $features = $obj['features'];
                                            $geominser_query = "INSERT INTO public.volcanic_area(
                                            gdacs_id, crisis_event_episode, polygontype, geom)
                                            VALUES (?, ?, ?, st_setsrid(ST_GeomFromGeoJSON(?),4326))";

                                            $stmt = $connection->prepare($geominser_query);
                                            $params = array($gdacs_id, $crisis_event_episode, $polygontype, $geometry);

                                            if ($stmt->execute($params)) {
                                                print "\n";
                                                print_r(' --> INSERITI FLOOD IN  FLOODS AREA ' . $gdacs_id . " with alert level -> " . $polygontype);
                                                print "\n";
                                            } else {
                                                print "\n";
                                                print "\n";
                                                print_r(' --> IMPOSSIBILE INSERIRE CICLONI IN  FLOODS AREA ' . $gdacs_id . " with alert level -> " . $polygontype);
                                                print "\n";
                                            }
                                        }// eliminare QUESTO
                                    }
                                }
                            }else {
                              print "\n";
                              print '************** LAST RECORD GIA INSERITO **************';
                              print "\n";
                            }
                        }else {
                          print "\n";
                          print '**************EVENTO GREEN PASSO PROSSIMO EVENTO **************';
                          print "\n";
                        }
                    } else {
                        print "\n";
                        print '**************CONTROLLO DATA NON PASSATO**************';
                        print "\n";
                    }
                } else {

                    print "\n";
                    print '**************IMPOSSIBILE CARICARE FIL XML**************';
                    print "\n";
                }
            } else {
              print "\n";
              print '*+*+*+*+*+*+*+*+*+*+*+*+*+*+ BUG NELLO SCRIPT EVENTO SALTATO *+*+*+*+*+*+*+*+*+*+*+*+*+*+';
              print "\n";
            }
            /*  } else {
              print "\n";
              print 'CONTROLLO DATA NON PASSATO';
              print "\n";
              } */
        } else { // TROVATO ID EVENTO IN CUCLONES
            // code...
        }
    } catch (\Exception $e) {
        pass;
    }
}

  function getxml($url, $tipo_calamita, $tipo_file, $evento_num, $episodio_num, $prefisso) {

      if ($episodio_num !== "") {
          $linkXML = "/datareport/resources/" . $tipo_calamita . "/" . $evento_num . $prefisso . $evento_num . "_" . $episodio_num . $tipo_file;
      } elseif ($episodio_num == "") {

          $linkXML = "/datareport/resources/" . $tipo_calamita . "/" . $evento_num . $prefisso . $evento_num . $tipo_file;
      }
      try {
          //$linkXML = "/datareport/resources/" . $tipo_calamita . "/" . $evento . $prefisso . $evento_num . "_" . $episodio_num . $tipo_file;
          $urllistaeventi = $url . str_replace('"', '', $linkXML);
          $getfile = file_get_contents($urllistaeventi);
          if ($getfile !== false AND ! empty($getfile)) {
              $getfile = str_replace('<gdacs:', '<', $getfile);
              $getfile = str_replace('</gdacs:', '</', $getfile);
              $getfile = str_replace('<georss:', '<', $getfile);
              $getfile = str_replace('</georss:', '</', $getfile);
          } else {
            if ($getfile == false or empty($getfile)) {
                  $linkXML = "/datareport/resources/" . $tipo_calamita . "/" . $evento_num . $prefisso . $evento_num . $tipo_file;
                  $urllistaeventi = $url . str_replace('"', '', $linkXML);
                  $getfile = file_get_contents($urllistaeventi);
              }
              if ($getfile == false or empty($getfile)) {
                $linkXML = "/datareport/resources/" . $tipo_calamita . "/" . $evento_num . "/cap_" . $evento_num . $tipo_file;
                  $urllistaeventi = $url . str_replace('"', '', $linkXML);

                  $getfile = file_get_contents($urllistaeventi);

              }elseif  ($getfile !== false AND ! empty($getfile)) {
                  print "<br>\n";
                  print "urllistaeventi 4 <br>\n";
                  print $urllistaeventi;
                  print "<br>\n";
                  $getfile = str_replace('<gdacs:', '<', $getfile);
                  $getfile = str_replace('</gdacs:', '</', $getfile);
                  $getfile = str_replace('<georss:', '<', $getfile);
                  $getfile = str_replace('</georss:', '</', $getfile);
              } else {
                  print "<br>\n";
                  print '************** ESEGUITI TUTTI I CONTROLLI IMPOSSIBILE GET FILE XML**************';
                  print "<br>\n";
              }
          }
      } catch (\Exception $e) {
          print "<br>\n";
          print '**************IMPOSSIBILE GET FILE XML**************';
          print "<br>\n";
      }


      $xml = new SimpleXMLElement($getfile);

      return $xml;
  }
