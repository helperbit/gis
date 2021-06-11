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
        , ST_AsGeoJSON(st_transform(lg.inviluppo_buffer_evento,4326))::json As geometry
        , row_to_json((SELECT l FROM 
        (SELECT 
        lg.id_evento, 
        start_time, 
        last_shake_time, 
        max_magnitude, 
        popolazione_coinvolta,
        num_comuni, 
        regione, 
        lg.country,
        affected_countries,
        placca_ricadente, 
        placca_secondaria, 
        getcity(citta_piu_vicina) as citta_piu_vicina, 
        distanza_citta_piu_vicina, 
        ST_CardinalDirection(radians(direzione_citta_piu_vicina)) as direzione_citta_piu_vicina,
        pop_citta_piu_vicina,
        abitato_piu_vicino, 
        distanza_abitato_piu_vicino,
        mountain_range, 
        distanza_faglia,is_sea,
        usgs_name_first,
        usgs_name_max,
        tsunami,
        n_shakes,
        typical_depth,
        capitali.population as capital_population, 
        capitali.name_engl as capital_name,
        st_distance(st_centroid(lg.inviluppo_buffer_evento),st_transform(capitali.geom,900913)) as metri_capitale,
        case 
            when (st_distance(st_transform(max_epicentro, 4326),country_centroid) * st_distance(st_transform(max_epicentro, 4326),country_centroid) *3.14) <(area) * 0.5 then 'MIDDLE'
            else 
            ST_CardinalDirection((ST_Azimuth(country_centroid,st_transform(max_epicentro, 4326))))
        end 
        as country_direction,
        
       old_event,       
       EXTRACT(YEAR FROM start_time::date) -EXTRACT(YEAR FROM old_data_evento::date)  as years_from_last_strong_event, 
       old_data_evento as data_ultimo_evento,
       old_mag as max_magnitude_ultimo_evento,
       old_deaths as caduti_ultimo_evento, 
       old_houses_destroyed as case_distrutte_ultimo_evento,
       old_tsunami as tzunami_ultimo_evento,
       old_dam_dollars as danni_in_milioni_dollari_ultimo_evento,ne_10m_time_zones.zone ,
       usgs_name_all 
        )

        As l
          )) As properties
       FROM eventi_terremoto 

       As lg  left join capitali on capitali.iso_3c = lg.country
       left join  (select distinct on (eventi_terremoto.id_evento)old_earthquakes.id, eventi_terremoto.id_evento, true as old_event,
  old_data_evento, old_tsunami, old_depth, old_mag, old_country, 
       old_deaths, old_injuries, old_dam_dollars, old_houses_destroyed 
       from old_earthquakes,eventi_terremoto
       where st_intersects(inviluppo_buffer_evento,st_transform(geom,900913)) 
  and max_magnitude < old_mag and EXTRACT(YEAR FROM old_data_evento::date) < 2014 
  order by eventi_terremoto.id_evento,old_data_evento DESC ) as ciao
  on ciao.id_evento = lg.id_evento 
  LEFT join countries_centroid on countries_centroid.iso_3digit = lg.country 
  LEFT JOIN ne_10m_time_zones on st_within(st_transform(lg.max_epicentro,4326),ne_10m_time_zones.geom)
  
WHERE lg.id_evento = $id_evento
        ) As f )  As fc;";


$result = query($connection, $stringaQuery);
if ($result->rowCount() > 0) {
    $row = $result->fetch();
    $json = $row["row_to_json"];

    echo $json;
} else {
    echo "false";
}


