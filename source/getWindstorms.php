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


//$epoch = 1427105844;
$connection = connetti();

$stringaQuery = "SELECT row_to_json(fc)
     FROM ( SELECT 'FeatureCollection' As type, array_to_json(array_agg(f)) As features
     FROM (SELECT 'Feature' As type
        , ST_AsGeoJSON(windstorms.geom)::json As geometry
        , row_to_json((SELECT l FROM
        (SELECT windstorms.gdacs_id as id_evento, created_at as start_time, crisis_alert_level, windstorms.crisis_event_episode, crisis_population, crisis_severity, crisis_severity_hash,
        crisis_vulnerability, crisis_vulnerability_hash, dc_date, dc_description, dc_title, gn_parent_country,
        regione,
        windstorms.country,
        getcity(citta_piu_vicina) as citta_piu_vicina,
        distanza_citta_piu_vicina,
        ST_CardinalDirection(radians(direzione_citta_piu_vicina)) as direzione_citta_piu_vicina,
        pop_citta_piu_vicina,
        abitato_piu_vicino,
        distanza_abitato_piu_vicino,
        is_sea,
        capitali.population as capital_population,
        capitali.name_engl as capital_name,
        st_distance(st_transform(windstorms.geom,900913),st_transform(capitali.geom,900913)) as metri_capitale,
        case
            when (st_distance(st_transform(windstorms.geom,4326),country_centroid) * st_distance(st_transform(windstorms.geom, 4326),country_centroid) *3.14) <(area) * 0.5 then 'MIDDLE'
            else
            ST_CardinalDirection((ST_Azimuth(country_centroid,st_transform(windstorms.geom, 4326))))
        end
        as country_direction
       )
         As l
          )) As properties
       FROM windstorms   left join capitali on capitali.iso_3c = windstorms.country   LEFT join countries_centroid on countries_centroid.iso_3digit = windstorms.country
        ) As f )  As fc;";


$result = query($connection, $stringaQuery);
if ($result->rowCount() > 0) {
    $row = $result->fetch();
    $json = $row["row_to_json"];

    echo $json;
} else {
    echo "false";
}
