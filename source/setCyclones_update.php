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

include_once __DIR__ . '/gestioneDb.php';

$sel = "Select st_x(geom) as x, st_y(geom) as y, sigimera_id from floods";

$connection = connetti();

$result = query($connection, $sel);
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $x = $row["x"];
    $y = $row["y"];
    $sigimera_id = $row["sigimera_id"];


    $stringaUpdateEvPredCountry = "SELECT iso_3digit as country,is_sea, sea
                                from world_bound
                                where ST_Intersects(st_setsrid(ST_MakePoint(?,?),4326),world_bound.geom) order by is_sea limit 1";
    $stmt0 = $connection->prepare($stringaUpdateEvPredCountry);
    $stmt0->execute(array($x, $y));
//echo $stringaUpdateEvPred;
    $rowUpdateEvPredCountry = $stmt0->fetch(PDO::FETCH_ASSOC);
    $country = pg_escape_string($rowUpdateEvPredCountry["country"]);
    if (!$country)
        $country = "";
    $is_sea = $rowUpdateEvPredCountry["is_sea"];
    $sea = '';
    if ($is_sea) {
        $is_sea = "true";
        $sea = pg_escape_string($rowUpdateEvPredCountry["sea"]);
    } else {
        //echo "ciao";
        $is_sea = "false";
    }

    $stringaUpdateEvPred = "SELECT regions.sr_adm0_a3 as country, regions.name as regione  from regions
                                where ST_Intersects(ST_MakeValid(st_transform(st_setsrid(ST_MakePoint(?,?),4326),3857)),regions.geom) limit 1";
    $stmt2 = $connection->prepare($stringaUpdateEvPred);
    $stmt2->execute(array($x, $y));
    $rowUpdateEvPred = $stmt2->fetch(PDO::FETCH_ASSOC);

    $regione = pg_escape_string($rowUpdateEvPred["regione"]);
    if (!$regione)
        $regione = $sea;
    $stringaUpdateEvPred2 = "SELECT coalesce(name1,'') ||', '|| coalesce(name2,'') ||', '|| coalesce(name3,'') ||', '|| coalesce(name4,'') ||', '|| coalesce(name5,'') as citta_piu_vicina , degrees(ST_Azimuth(centroids.geom, ST_transform(st_setsrid(ST_MakePoint(?,?),4326),3857))) as direzione_citta_piu_vicina, "
            . " ST_Distance(centroids.geom, st_transform(st_setsrid(ST_MakePoint(?,?),4326),3857)) as distanza_citta_piu_vicina, centroids.p10a as pop_citta_piu_vicina 
                                from centroids where centroids.p10a > 100000 order by ST_Distance(centroids.geom,ST_transform(st_setsrid(ST_MakePoint(?,?),4326),3857)) ASC limit 1";
    $stmt3 = $connection->prepare($stringaUpdateEvPred2);
    $stmt3->execute(array($x, $y, $x, $y, $x, $y));
    $rowUpdateEvPred2 = $stmt3->fetch(PDO::FETCH_ASSOC);
    $citta_piu_vicina = pg_escape_string($rowUpdateEvPred2["citta_piu_vicina"]);
    $pop_citta_piu_vicina = pg_escape_string($rowUpdateEvPred2["pop_citta_piu_vicina"]);
    $direzione_citta_piu_vicina = pg_escape_string($rowUpdateEvPred2["direzione_citta_piu_vicina"]);
    if (!$citta_piu_vicina)
        $citta_piu_vicina = "";
    $distanza_citta_piu_vicina = $rowUpdateEvPred2["distanza_citta_piu_vicina"];
    if (!$distanza_citta_piu_vicina)
        $distanza_citta_piu_vicina = Null;
    if (!$pop_citta_piu_vicina)
        $pop_citta_piu_vicina = Null;
    if (!$direzione_citta_piu_vicina)
        $direzione_citta_piu_vicina = Null;

///////////////
    $stringaGetSecondCity = "SELECT coalesce(name1,'') ||', '|| coalesce(name2,'') ||', '|| coalesce(name3,'') ||', '|| coalesce(name4,'') ||', '|| coalesce(name5,'') as abitato_piu_vicino ,  
                         ST_Distance(centroids.geom, st_transform(st_setsrid(ST_MakePoint(?,?),4326),3857)) as distanza_abitato_piu_vicino 
                                from centroids order by ST_Distance(centroids.geom,ST_transform(st_setsrid(ST_MakePoint(?,?),4326),3857)) ASC limit 1";
    $stmt4 = $connection->prepare($stringaGetSecondCity);
    $stmt4->execute(array($x, $y, $x, $y));
    $rowGetSecondCity = $stmt4->fetch(PDO::FETCH_ASSOC);
    $abitato_piu_vicino = pg_escape_string($rowGetSecondCity["abitato_piu_vicino"]);
    if (!$abitato_piu_vicino)
        $abitato_piu_vicino = "";
    $distanza_abitato_piu_vicino = $rowGetSecondCity["distanza_abitato_piu_vicino"];
    if (!$distanza_abitato_piu_vicino)
        $distanza_abitato_piu_vicino = Null;

    $updateSql = 'UPDATE public.floods
	SET regione=?, country=?, citta_piu_vicina=?, direzione_citta_piu_vicina=?, abitato_piu_vicino=?, 
    distanza_abitato_piu_vicino=?, is_sea=?, pop_citta_piu_vicina=?, distanza_citta_piu_vicina = ?
    where sigimera_id = ?';
    $stmt5 = $connection->prepare($updateSql);
    $stmt5->execute(array($regione, $country, $citta_piu_vicina, $direzione_citta_piu_vicina, $abitato_piu_vicino, $distanza_abitato_piu_vicino, $is_sea, $pop_citta_piu_vicina,$distanza_citta_piu_vicina, $sigimera_id));
}

