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


include_once __DIR__ . '/gestioneDb.php';
$connection = connetti();
//$id_evento = 55514;
$maxMin = "select (select count(distinct id_evento) from shakemap), id_evento FROM public.shakemap where id_evento not in (select id_evento from shakemap_clean) group by id_evento";
$resultmaxMin = query($connection, $maxMin);
$n = 0;
while ($rowmaxMin = $resultmaxMin->fetch(PDO::FETCH_ASSOC)) {
    $id_evento = $rowmaxMin["id_evento"];
    $count = $rowmaxMin["count"];
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

    echo $n . " di " . $count . " /n";
    $n++;
}
?>