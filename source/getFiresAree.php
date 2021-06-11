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
include_once __DIR__.'/gestioneDb.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
header('Content-Type: application/json');
//$parametri = $_SERVER['QUERY_STRING'];
$id_evento = pg_escape_string($_GET["id_evento"]);

//$epoch = 1427105844;
$connection = connetti();

$stringaQuery = "SELECT row_to_json(fc)
     FROM ( SELECT 'FeatureCollection' As type, array_to_json(array_agg(f)) As features
     FROM (SELECT 'Feature' As type
        , ST_AsGeoJSON(st_simplify(lg.inviluppo_evento,0.0001))::json As geometry
        , row_to_json((SELECT l FROM
        (SELECT id_evento, start_time ,end_time, citta_piu_vicina,
        distanza_citta_piu_vicina, direzione_citta_piu_vicina,
       abitato_piu_vicino, distanza_abitato_piu_vicino, pop_citta_piu_vicina,sr_adm0_a3 as country, name as regione
       )

        As l
          )) As properties
       FROM eventi_fires As lg LEFT JOIN regions on ST_within(st_transform(ST_centroid(inviluppo_evento),3857),regions.geom)

        where ST_GeometryType(lg.inviluppo_evento) = 'ST_Polygon' and st_isvalid(lg.inviluppo_evento)
        and id_evento = $id_evento

        ) As f )  As fc;";


$result = query($connection, $stringaQuery);
if ($result->rowCount() > 0) {
    $row = $result->fetch();
    $json = $row["row_to_json"];

    echo $json;
} else {
    echo "false";
}
