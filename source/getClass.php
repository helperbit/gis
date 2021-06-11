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

//interrogo evento ed interseco sezioni con areee interessate e areee tot sezione, poi interrogo pop sezione e calcolo

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

";
//echo $stringaQuery;
} else {
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
}
$result = query($connection, $stringaQuery);
$return = array();
$return = "{";
if ($result->rowCount() > 0) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {

        $return = $return .'"'. $row["risk"]. '":{"class":"' . $row["class"] . '", "source":"' . $row["source"] . '"},';
    }
    $return = rtrim($return, ",");
    $return = $return . "}";
    echo $return;
} else {


    echo "false";
}





    