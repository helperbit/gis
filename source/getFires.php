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
//$parametri = $_SERVER['QUERY_STRING'];
header('Content-Type: application/json');

//$epoch = 1427105844;
$connection = connetti();

$stringaQuery = "SELECT row_to_json(fc)
     FROM ( SELECT 'FeatureCollection' As type, array_to_json(array_agg(f)) As features
     FROM (SELECT 'Feature' As type
        , ST_AsGeoJSON(st_centroid(lg.inviluppo_evento))::json As geometry
        , row_to_json((SELECT l FROM
        (SELECT
        id_evento,
        start_time ,
        end_time,
        citta_piu_vicina,
        distanza_citta_piu_vicina,
        direzione_citta_piu_vicina,
       abitato_piu_vicino,
       affected_countries,
       distanza_abitato_piu_vicino,
       pop_citta_piu_vicina,
       st_area(st_transform((lg.inviluppo_evento),3857)) as area,
       sr_adm0_a3 as country,
       name as regione,
       capitali.population as capital_population,
        capitali.name_engl as capital_name,
        st_distance(st_transform(st_centroid(lg.inviluppo_evento),3857),st_transform(capitali.geom,3857)) as metri_capitale,
        case
            when (st_distance(st_centroid(lg.inviluppo_evento),country_centroid) * st_distance(st_centroid(lg.inviluppo_evento),country_centroid) *3.14) <(area) * 0.5 then 'MIDDLE'
            else
            ST_CardinalDirection((ST_Azimuth(country_centroid,st_centroid(inviluppo_evento))))
        end
        as country_direction

       )

        As l
          )) As properties
       FROM eventi_fires As lg LEFT JOIN regions on ST_within(st_transform(ST_centroid(inviluppo_evento),3857),regions.geom)
       left join capitali on capitali.iso_3c = sr_adm0_a3
       LEFT join countries_centroid on countries_centroid.iso_3digit = sr_adm0_a3



        ) As f )  As fc;";


$result = query($connection, $stringaQuery);
if ($result->rowCount() > 0) {
    $row = $result->fetch();
    $json = $row["row_to_json"];

    echo $json;
} else {
    echo "false";
}
