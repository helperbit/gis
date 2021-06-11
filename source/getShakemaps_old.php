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

ini_set('error_reporting', 1);
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
  and net || code not in (select usgs_name from  public.shakemap)
  --and code ='1000731j'  ";
//echo $stringaQuery;
$result = query($connection, $stringaQuery);

if ($result->rowCount() > 0) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {

        $id_evento = $row["id_evento"];
        $usgs_name = $row["usgs_name"];
        //https://earthquake.usgs.gov/earthquakes/eventpage/us1000hwaj/shakemap/intensity
        //$url = $row["url"] . "#shakemap"; USGS CAMBIA PAGINA
        $url = $row["detail"];
        //echo $url . "<br>";
        //$id_evento = $row["id_evento"];
        $html = file_get_contents ($url);
        if ($html) {
            $primo = explode('"download/shape.zip":', $html)[1];
            $secondo = explode('"url":"', $primo)[1];
            $link = explode('"}', $secondo)[0];
            /*foreach ($html->find('a') as $element) {
                if (strpos($element->href, 'shape.zip') !== false) {
                    //$link = "http://earthquake.usgs.gov" . $element->href;
                    $link = $element->href;
                }
            }*/
            if (isset($link)) {

                $code = $row["code"];

                $now = time();
                $dirUpload = "zipTemp/";
                $dirDecompressa = $dirUpload . "decompresse/" . $now;
                $newfile = $dirUpload . $now . ".zip";
                echo "Download file zip " . $link . " <br>";
                if (copy($link, $newfile)) {

                    $zip = new ZipArchive;
                    $res = $zip->open($newfile);
                    if ($res === TRUE) {
                        mkdir($dirDecompressa);
                        echo "Decompressione file zip " . " <br>";
                        $estrai = $zip->extractTo($dirDecompressa, array('mi.shp', 'mi.prj', 'mi.dbf', 'mi.shx'));
                        if ($estrai) {

                            $res = $zip->close();
                            unlink($newfile);
                            //cancello tabella
                            $tabProv = "shakes";
                            $delete = "Delete from $tabProv";
                            $resultDelete = query($connection, $delete);
                            if ($resultDelete) {
                                echo "Caricamento nel db delle shakemaps..." . " <br>";
                                $shp = realpath(dirname(__FILE__)) . "/" . $dirDecompressa . "/mi.shp";
                                $esegui = 'ogr2ogr -progress -f PostgreSQL PG:"host=' . $host . ' user=' . $user . ' port=' . $port . ' dbname=' . $dbName . ' password=' . $password . '" "' . $shp . '" -where "paramvalue >=4" -skipfailures -append -nln ' . $tabProv . " -nlt PROMOTE_TO_MULTI ";
                                echo 'Launching: ' . $esegui;
                                echo "\n";
                                echo exec($esegui);
                                /* $q = "UPDATE public.world_earthquakes_1900_55 set shake_lavorate = true where (net || code) = '$usgs_name'";
                                  $resultq = query($connection, $q); */
                                $insert = "INSERT INTO shakemap(
		                usgs_name, id_evento, mag, geom)
		                    SELECT  '$usgs_name', $id_evento, paramvalue::integer, st_multi(st_union(geom))
		                      FROM shakes where paramvalue >= 4 group by paramvalue::integer;";
                                $resultInsert = query($connection, $insert);
                                if ($resultInsert && $resultInsert->rowCount() > 0) {
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
                                    email_sender("NEW SYSTEM: Aggiunta Shakemap a evento $id_evento", "Aggiunta Shakemap a evento $id_evento");
                                    //deleteDir($dirDecompressa);
                                } else {
                                    //email_sender("NEW SYSTEM: Problema Shakemap a evento $id_evento", "Problema Shakemap a evento $id_evento");
                                    echo "Problema: impossibile inserire gli elementi in shakemap" . " <br>";
                                }
                            } else {
                                echo "Problema: impossibile ripulire la tabella di appoggio" . " <br>";
                            }
                        }
                    }
                } else {

                    echo "Shakemap non trovata" . " <br><br>";
                }
            }
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
