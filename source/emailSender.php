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

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

function email_sender($subject, $body) {
    $addresses = ["carbone.gia@gmail.com","guidobartu@gmail.com","dak.linux@gmail.com"];
    $email_from = "helperbit.gis@gmail.com";
    $name_from = "Helperbit GIS";
    $mail = new PHPMailer(true);
    $mail->IsSMTP(); // telling the class to use SMTP
    $mail->SMTPAuth = true; // enable SMTP authentication
    $mail->SMTPSecure = "ssl"; // sets the prefix to the servier
    $mail->Host = "smtp.gmail.com"; // sets GMAIL as the SMTP server
    $mail->Port = 465; // set the SMTP port for the GMAIL server
    $mail->Username = "helperbit.gis@gmail.com"; // GMAIL username
    $mail->Password = "h3lp3b1t"; // GMAIL password
    $mail->IsHTML(true); 
    foreach ($addresses as $address) {
        $mail->AddAddress($address);
    }
    $mail->SetFrom($email_from, $name_from);
    $mail->Subject = $subject;
    $mail->Body = $body;

    try {
        $mail->Send();
        $risultato = '{ "status":"true"}';
    } catch (Exception $e) {
        $risultato = '{ "status":"problema con invio email"}';
    }
    return $risultato;
}

  