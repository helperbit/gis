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
error_reporting(E_ALL);
include_once __DIR__.'/gestioneDb.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
header('Content-Type: application/json');
//$parametri = $_SERVER['QUERY_STRING'];
$lon = pg_escape_string($_GET["lon"]);
$lat = pg_escape_string($_GET["lat"]);

//$epoch = 1427105844; 
$connection = connetti();

$stringaQuery = "SELECT id, zone, utc_format,  places       
  FROM public.ne_10m_time_zones
  where st_within(ST_SetSRID(ST_Point($lon, $lat),4326), geom)";
//echo $stringaQuery;

$result = query($connection, $stringaQuery);
if ($result->rowCount() > 0) {
    $row = $result->fetch(PDO::FETCH_ASSOC);
    $json = json_encode($row);
    echo $json;
} else {
    echo "false";
}


