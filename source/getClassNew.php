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
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
header('Content-Type: application/json');
$lon = pg_escape_string($_GET["lon"]);
$lat = pg_escape_string($_GET["lat"]);
include_once __DIR__ . '/gestioneDb.php';
$connection = connetti();
$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
$stringaQuery = "              
with foo as (
SELECT ST_Value(rast, st_transform(ST_SetSRID(ST_MakePoint($lon,$lat),4326),3857)) as class_value

  FROM public.classi_pericolosita
)
select  
    'earthquake' as risk, 
case when class_value = 0 then 'norisk'
  when class_value = 1 then 'low'
  when class_value = 2 then 'medium'
  when class_value = 3 then 'high'
  end as class,
  'helperbit' as source

from  foo

UNION (
with foo as (
SELECT ST_Value(rast, st_transform(ST_SetSRID(ST_MakePoint($lon,$lat),4326),3857)) as class_value

  FROM public.classi_pericolosita_inc
)
select  
    'wildfire' as risk, 
case when class_value is null then 'norisk'
  when class_value = 1 then 'low'
  when class_value = 2 then 'medium'
  when class_value = 3 then 'high'
  end as class,
  'helperbit' as source

from  foo)

";
//interrogo evento ed interseco sezioni con areee interessate e areee tot sezione, poi interrogo pop sezione e calcolo
$stringaQuery0 = "SELECT  iso_3digit
  FROM public.world_bound
  where is_sea = false and st_within(ST_SetSRID(ST_MakePoint($lon,$lat),4326), geom)";
$result0 = query($connection, $stringaQuery0);

if ($result0->rowCount() > 0) {
    $row0 = $result0->fetch(PDO::FETCH_ASSOC);
    $iso = $row0["iso_3digit"];
    if ($iso == 'ITA') {
        $stringaQuery1 = "SELECT (4 - substring(classe from 1 for 1)::int) as classe, 'DPC' as source
            FROM public.classi_sismica_2015 where st_within(st_transform(ST_SetSRID(ST_MakePoint($lon,$lat),4326),3857),st_transform(geom,3857))
          ";
        //echo $stringaQuery;
        $result1 = query($connection, $stringaQuery1);

        if ($result1->rowCount() > 0) {
            $row1 = $result1->fetch(PDO::FETCH_ASSOC);
            $classeTerr = $row1["classe"];
            $source = $row1["source"];
            $stringaQuery = "        
            with foo as (
            SELECT $classeTerr::integer as class_value, '$source'::text as source

            )
            select  
                'earthquake' as risk, 
            case when class_value = 0 then 'norisk'
              when class_value = 1 then 'low'
              when class_value = 2 then 'medium'
              when class_value = 3 then 'high'
              end as class,
              foo.source

            from  foo

            UNION (
            with foo as (
            SELECT ST_Value(rast, st_transform(ST_SetSRID(ST_MakePoint($lon,$lat),4326),3857)) as class_value

              FROM public.classi_pericolosita_inc
            )
            select  
                'wildfire' as risk, 
            case when class_value is null then 'norisk'
              when class_value = 1 then 'low'
              when class_value = 2 then 'medium'
              when class_value = 3 then 'high'
              end as class,
              'helperbit' as source

            from  foo)
            UNION (with foo2 as (
            SELECT legenda as class_value  FROM public.rischio_alluvione where st_intersects(geom, ST_SetSRID(ST_MakePoint($lon,$lat),4326))     
            )
                select  
                'flood' as risk, 
                case when 
                (select count(class_value) from foo2 ) = 0  then 'norisk'
                else (select 
                case when class_value = 'NULLO' or class_value = 'N.D.' or class_value = '' or class_value is null then 'norisk'
                 when class_value = 'ELEVATO' or class_value = 'MOLTO ELEVATO' then 'high'
                 when class_value = 'MEDIO'  then 'medium'
                 when class_value = 'MODERATO'  then 'low' end as class

                from foo2)
              end as class,
              'PAI' as source)
            UNION (with foo3 as (
            SELECT legenda as class_value  FROM public.rischio_frane where st_within(ST_SetSRID(ST_MakePoint($lon,$lat),4326),geom )     
            )
                select  
                'Landslide' as risk, 
                case when 
                (select count(class_value) from foo3 ) = 0  then 'norisk'
                else (select 
                case 
                 when class_value = 'ELEVATO' or class_value = 'MOLTO ELEVATO' then 'high'
                 when class_value = 'MEDIO'  then 'medium'
                 when class_value = 'MODERATO' or class_value = 'SITO DI ATTENZIONE'   then 'low' 
		else 'norisk' end as class
                from foo3)
              end as class,
              'PAI' as source)
              UNION ALL (with foo4 as (
            SELECT  legenda as class_value  FROM public.rischio_valanga  where st_within(ST_SetSRID(ST_MakePoint($lon,$lat),4326),geom )     
            )
                select  
                'avalanche' as risk, 
                case when 
                (select count(class_value) from foo4 ) = 0  then 'norisk'
                else (select 
                case 
                 when class_value = 'ELEVATO' or class_value = 'MOLTO ELEVATO' then 'high'
                 when class_value = 'MEDIO'  then 'medium'
                 when class_value = 'MODERATO'    then 'low' 
		else 'norisk' end as class
                from foo4)
              end as class,
              'PAI' as source)
            ";
        }
    } elseif ($iso == 'JPN') {
        $stringaQuery1 = "SELECT ST_Value(rast, st_transform(ST_SetSRID(ST_MakePoint($lon,$lat),4326),3857)) as classe, 'j-shis' as source
  FROM public.classi_pericolosita_jap where st_intersects(rast, st_transform(ST_SetSRID(ST_MakePoint($lon,$lat),4326),3857))
  limit 1
          ";
        //echo $stringaQuery;
        $result1 = query($connection, $stringaQuery1);

        if ($result1->rowCount() > 0) {
            $row1 = $result1->fetch(PDO::FETCH_ASSOC);
            $classeTerr = $row1["classe"];
            $source = $row1["source"];
            $stringaQuery = "        
            with foo as (
            SELECT $classeTerr::integer as class_value, '$source'::text as source

            )
            select  
                'earthquake' as risk, 
            case when class_value = 0 then 'very-low'
              when class_value = 1 then 'low'
              when class_value = 2 then 'medium'
              when class_value = 3 then 'high'
              when class_value = 4 then 'very-high'
              end as class,
              foo.source

            from  foo

            UNION (
            with foo as (
            SELECT ST_Value(rast, st_transform(ST_SetSRID(ST_MakePoint($lon,$lat),4326),3857)) as class_value

              FROM public.classi_pericolosita_inc
            )
            select  
                'wildfire' as risk, 
            case when class_value is null then 'norisk'
              when class_value = 1 then 'low'
              when class_value = 2 then 'medium'
              when class_value = 3 then 'high'
              end as class,
              'helperbit' as source

            from  foo)

            ";
        }
    }
}
$result = query($connection, $stringaQuery);
$return = array();
$return = "{";
if ($result->rowCount() > 0) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {

        $return = $return . '"' . $row["risk"] . '":{"class":"' . $row["class"] . '", "source":"' . $row["source"] . '"},';
    }
    $return = rtrim($return, ",");
    $return = $return . "}";
    echo $return;
} else {


    echo "false";
}
    
