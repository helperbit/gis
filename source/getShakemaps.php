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

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once __DIR__ . '/emailSender.php';

include_once __DIR__ . '/gestioneDb.php';
include_once __DIR__ . '/simple_html_dom.php';
$oggi = time();
//$annoFa = ($oggi - 31556926) * 1000;
// 1262304000 - 2010
$dueMesiFa = ($oggi - 8184000) * 1000;
//$annoFa = 1262304000;
$connection = connetti();
$stringaQuery = "SELECT
  code,id_evento, (net || code) as usgs_name, shake_lavorate, detail
FROM
  public.world_earthquakes_1900_55
  where is_lavorato = true
  and time::bigint > $dueMesiFa
  and net || code not in (select usgs_name from  public.shakemap)";

//echo $stringaQuery;
$result = query($connection, $stringaQuery);

if ($result->rowCount() > 0) {
    $array_polygons = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {

        $id_evento = $row["id_evento"];
        $usgs_name = $row["usgs_name"];
        //https://earthquake.usgs.gov/earthquakes/eventpage/us1000hwaj/shakemap/intensity
        //$url = $row["url"] . "#shakemap"; USGS CAMBIA PAGINA
        $url = $row["detail"];
        //echo $url . "<br>";
        //$id_evento = $row["id_evento"];
        $html = file_get_contents($url);
        if ($html) {


            try {

                $primo = explode('"download/shakemap.kmz":', $html)[1];
                $nomefile = "/shakemap.kml";
                throw new Exception();
            } catch (\Exception $e) {
                
            }

            print "\n";
            print "primo";
            print "\n";
            print $primo;
            print "\n";
            if (!$primo) {
                $primo = explode('"download/polygons_mi.kmz":', $html)[1];
                $nomefile = "/polygons_mi.kml";
            }

            //$primo = explode('"download/shape.zip":', $html)[1];
            //$primo = explode('"download/shakemap.kmz":', $html)[1]; // creare evento per download/polygons_mi.kmz"
            $secondo = explode('"url":"', $primo)[1];
            $link = explode('"}', $secondo)[0];
            echo $link . " <br>";
            /* foreach ($html->find('a') as $element) {
              if (strpos($element->href, 'shape.zip') !== false) {
              //$link = "http://earthquake.usgs.gov" . $element->href;
              $link = $element->href;
              }
              } */
            if (isset($link)) {

                $code = $row["code"];
                $now = time();
                $dirUpload = "zipTemp/";
                $dirDecompressa = $dirUpload . "decompresse/" . $now;
                $newfile = $dirUpload . $now . ".kmz";
                //echo "Download file zip " . $link . " <br>";
                print "\n";
                echo "Download file KMZ " . $link . " <br>";
                print "\n";
                if (copy($link, $newfile)) {

                    $zip = new ZipArchive;
                    $res = $zip->open($newfile);
                    if ($res === TRUE) {
                        mkdir($dirDecompressa);
                        //echo "Decompressione file zip " . " <br>";
                        print_r("Decompressione file KMZ " . " <br>");
                        //shakemap2 MMI 4 Polygons
                        //array('mi.shp', 'mi.prj', 'mi.dbf', 'mi.shx')
                        $estrai = $zip->extractTo($dirDecompressa);
                        //$estrai = $zip->extractTo($dirDecompressa, array('mi.shp', 'mi.prj', 'mi.dbf', 'mi.shx'));


                        $res = $zip->close();
                        // INIZIO ELABORAZIONE KML
//$nameVerify = "MMI Polygons";

                        $file = $dirDecompressa . $nomefile;
                        $xml = simplexml_load_file($file) or die("Error: Cannot create object xml");
                        if ($xml) {
                            if ($nomefile == "/shakemap.kml") {  // code...
                                // ESTRARRE I DATI DAL KML
                                $firstfolders = $xml->Document->children();

                                foreach ($firstfolders as $firstfolder) {
                                    $name = $firstfolder->name;
                                    if ($name[0] == "MMI Polygons") {
                                        $childrens = $firstfolder->children();
                                        foreach ($childrens as $children) {
                                            $namechild = $children->name;
                                            $placemarkers = $children->Placemark;
                                            foreach ($placemarkers as $placemarker) {
                                                $name = $placemarker->name;
                                                //CONTROLLO SE APPARTIENE AD UN POLYGON MMI
                                                if (strpos($name, 'MMI') !== false) {
                                                    $coordinates = $placemarker->Polygon->asXML();
                                                    $mmi = explode(" ", $name);
                                                    //INSERT NEL DB (TABELLA shakemap ) I DATI
                                                    $insert = "INSERT INTO public.shakemap(
                                                 usgs_name, id_evento, mag, geom)
                                                VALUES ( '$usgs_name', $id_evento, $mmi[1]::integer, ST_Multi(ST_Force2D(ST_GeomFromKML('$coordinates'))));";
                                                    $stmt = $connection->prepare($insert);
                                                    if ($stmt->execute()) {
                                                        print_r("row con evento :" . $id_evento . " inserted" . "\n");
                                                        $deleteOld = "delete from shakemap_clean where id_evento = $id_evento";
                                                        $resultdeleteOld = query($connection, $deleteOld);
                                                        $ripulisci = "with
                                                            myq as (SELECT id, id_evento, mag, geom
                                                              FROM shakemap_vw
                                                              where id_evento = $id_evento
                                                            )
                                                            , my_ris as (
                                                            Select id, id_evento, mag, geom  from myq where mag = 10
                                                            union all
                                                                    Select id, id_evento, mag,
                                                                    case when (select st_union(geom) from myq where mag > 9 ) is null then geom
                                                                    else st_difference(geom,(select st_union(geom) from myq where mag > 9 )) end
                                                                    as geom
                                                                    from myq where mag = 9
                                                            union all
                                                                    Select id, id_evento, mag,
                                                                    case when (select st_union(geom) from myq where mag > 8 ) is null then geom
                                                                    else st_difference(geom,(select st_union(geom) from myq where mag > 8 )) end
                                                                    as geom
                                                                    from myq where mag = 8

                                                            union all
                                                                    Select id, id_evento, mag,
                                                                    case when (select st_union(geom) from myq where mag > 7 ) is null then geom
                                                                    else st_difference(geom,(select st_union(geom) from myq where mag > 7 )) end
                                                                    as geom
                                                                    from myq where mag = 7
                                                            union all
                                                                    Select id, id_evento, mag,
                                                                    case when (select st_union(geom) from myq where mag > 6 ) is null then geom
                                                                    else st_difference(geom,(select st_union(geom) from myq where mag > 6 )) end
                                                                    as geom
                                                                    from myq where mag = 6
                                                            union all
                                                                    Select id, id_evento, mag,
                                                                    case when (select st_union(geom) from myq where mag > 5 ) is null then geom
                                                                    else st_difference(geom,(select st_union(geom) from myq where mag > 5 )) end
                                                                    as geom
                                                                    from myq where mag = 5
                                                            union all
                                                                    Select id, id_evento, mag,
                                                                    case when (select st_union(geom) from myq where mag > 4 ) is null then geom
                                                                    else st_difference(geom,(select st_union(geom) from myq where mag > 4 )) end
                                                                    as geom
                                                                    from myq where mag = 4
                                                            )

                                                            INSERT INTO public.shakemap_clean(
                                                                        id, id_evento, mag, geom)

                                                            SELECT id, id_evento, mag, st_multi(geom) FROM my_ris
                                                            ";
                                                        $resultRipulisci = query($connection, $ripulisci);
                                                        echo "Caricamento completato <br><br>";
                                                        //deleteDir($dirDecompressa);
                                                    } else {
                                                        print_r("Problema: impossibile inserire gli elementi in shakemap");
                                                        print_r($stmt->errorInfo());
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                email_sender("NEW SYSTEM: Aggiunta Shakemap a evento $id_evento", "Aggiunta Shakemap a evento $id_evento");
                                                        
                            } elseif ($nomefile == "/polygons_mi.kml") {  // code...
                                array_push($array_polygons, $link);
                                // ESTRARRE I DATI DAL KML
                                $firstfolders = $xml->Document->Folder;
                                /*  print "\n";
                                  print "firstfolders : ";
                                  print "\n";
                                  //print_r($firstfolders);
                                  print "\n"; */

                                foreach ($firstfolders as $firstfolder) {
                                    $name = $firstfolder->name;
                                    /*  print "\n";
                                      print "firstfolder : ";
                                      print "\n";
                                      //print_r($firstfolder);
                                      print "-------------------------------------------------------------------------------- ";
                                      print "\n";
                                      //print $firstfolder;
                                      print "\n";
                                      print "\n";
                                      print "name : ";
                                      print "\n";
                                      print_r(explode(" ", $name[0]));
                                      print "\n"; */

                                    if (explode(" ", $name[0])[0] == "Intensity") {

                                        $placemarkers = $firstfolder->Placemark;

                                        /* print_r("name");
                                          print_r("\n");
                                          print_r($name);
                                          print_r("\n"); */

                                        $namechild = $name[0];
                                        // $namechild = $childrens->name;
                                        //$placemarkers = $children->Placemark;
                                        foreach ($placemarkers as $placemarker) {
                                            $name = $placemarker->name;
                                            /* print_r("TROVATO Pplacemarker con nome : ");
                                              print_r("\n");
                                              print_r($name);
                                              print_r("\n"); */

                                            //CONTROLLO SE APPARTIENE AD UN POLYGON MMI
                                            if (strpos($name, 'Intensity') !== false) {
                                                $coordinates = $placemarker->Polygon->asXML();
                                                $mmi = explode(" ", $name)[1];

                                                //INSERT NEL DB (TABELLA shakemap ) I DATI
                                                $insert = "INSERT INTO public.shakemap(
                                                 usgs_name, id_evento, mag, geom)
                                                VALUES ( '$usgs_name', $id_evento, $mmi::integer, ST_Multi(ST_Force2D(ST_GeomFromKML('$coordinates'))));";
                                                $stmt = $connection->prepare($insert);
                                                if ($stmt->execute()) {
                                                    print_r("\n");
                                                    print_r("row con evento :" . $id_evento . " inserted con  MI : " . $mmi . "\n");
                                                    $deleteOld = "delete from shakemap_clean where id_evento = $id_evento";
                                                    $resultdeleteOld = query($connection, $deleteOld);
                                                    $ripulisci = "with
                                                            myq as (SELECT id, id_evento, mag, geom
                                                              FROM shakemap_vw
                                                              where id_evento = $id_evento
                                                            )
                                                            , my_ris as (
                                                            Select id, id_evento, mag, geom  from myq where mag = 10
                                                            union all
                                                                    Select id, id_evento, mag,
                                                                    case when (select st_union(geom) from myq where mag > 9 ) is null then geom
                                                                    else st_difference(geom,(select st_union(geom) from myq where mag > 9 )) end
                                                                    as geom
                                                                    from myq where mag = 9
                                                            union all
                                                                    Select id, id_evento, mag,
                                                                    case when (select st_union(geom) from myq where mag > 8 ) is null then geom
                                                                    else st_difference(geom,(select st_union(geom) from myq where mag > 8 )) end
                                                                    as geom
                                                                    from myq where mag = 8

                                                            union all
                                                                    Select id, id_evento, mag,
                                                                    case when (select st_union(geom) from myq where mag > 7 ) is null then geom
                                                                    else st_difference(geom,(select st_union(geom) from myq where mag > 7 )) end
                                                                    as geom
                                                                    from myq where mag = 7
                                                            union all
                                                                    Select id, id_evento, mag,
                                                                    case when (select st_union(geom) from myq where mag > 6 ) is null then geom
                                                                    else st_difference(geom,(select st_union(geom) from myq where mag > 6 )) end
                                                                    as geom
                                                                    from myq where mag = 6
                                                            union all
                                                                    Select id, id_evento, mag,
                                                                    case when (select st_union(geom) from myq where mag > 5 ) is null then geom
                                                                    else st_difference(geom,(select st_union(geom) from myq where mag > 5 )) end
                                                                    as geom
                                                                    from myq where mag = 5
                                                            union all
                                                                    Select id, id_evento, mag,
                                                                    case when (select st_union(geom) from myq where mag > 4 ) is null then geom
                                                                    else st_difference(geom,(select st_union(geom) from myq where mag > 4 )) end
                                                                    as geom
                                                                    from myq where mag = 4
                                                            )

                                                            INSERT INTO public.shakemap_clean(
                                                                        id, id_evento, mag, geom)

                                                            SELECT id, id_evento, mag, st_multi(geom) FROM my_ris
                                                            ";
                                                    $resultRipulisci = query($connection, $ripulisci);
                                                    echo "Caricamento completato <br><br>";
                                                    //deleteDir($dirDecompressa);
                                                } else {
                                                    print_r("Problema: impossibile inserire gli elementi in shakemap");
                                                    print_r($stmt->errorInfo());
                                                }
                                            }
                                        }
                                    }
                                }
                                email_sender("NEW SYSTEM: Aggiunta Shakemap a evento $id_evento", "Aggiunta Shakemap a evento $id_evento");                                                   
                                
                            }
                        }
                    }
                }
            } else {

                echo "Shakemap non trovata" . " <br><br>";
            }
        } else {
            echo "Html Shakemap non trovato" . " <br><br>";
        }
    }
}

function deleteDir($dirPath) {
    if (!is_dir($dirPath)) {
        throw new InvalidArgumentException("$dirPath must be a directory");
    }
    if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
        $dirPath .= '/';
    }
    $files = glob($dirPath . '*', GLOB_MARK);
    foreach ($files as $file) {
        if (is_dir($file)) {
            /* self::deleteDir($file); */
            $this::deleteDir($file);
        } else {
            unlink($file);
        }
    }
    rmdir($dirPath);
}

?>
