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

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
header('Content-Type: application/json');
//$parametri = $_SERVER['QUERY_STRING'];
$idEvento = pg_escape_string($_GET["id_evento"]);

include_once __DIR__.'/gestioneDb.php';
$connection = connetti();

//interrogo evento ed interseco sezioni con areee interessate e areee tot sezione, poi interrogo pop sezione e calcolo

$stringaQuery = "SELECT 
  single_users.id_utente as affected_users
FROM 
  public.single_users, 
  public.eventi_terremoto
WHERE 
  id_evento = $idEvento
  AND 
  ST_within(single_users.geom,inviluppo_buffer_evento)";


$result = query($connection, $stringaQuery);
$return = array();
$return["idEvento"] = $idEvento;
//$risultato = '{"idEvento":$idEvento, affectedUsers: [0,1,2,5,100]}';
if ($result->rowCount() > 0) {
    
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $return["affectedUsers"][] = $row["affected_users"];
    }
    echo json_encode($return);

} else {
    echo "false";
}




