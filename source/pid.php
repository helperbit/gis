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

class pid {

    protected $filename;
    public $already_running = false;
   
    function __construct($directory) {
       
        $this->filename = $directory . '/' . basename($_SERVER['PHP_SELF']) . '.pid';
       
        if(is_writable($this->filename) || is_writable($directory)) {
           
            if(file_exists($this->filename)) {
                $pid = (int)trim(file_get_contents($this->filename));
                if(posix_kill($pid, 0)) {
                    $this->already_running = true;
                }
            }
           
        }
        else {
            die("Cannot write to pid file '$this->filename'. Program execution halted.\n");
        }
       
        if(!$this->already_running) {
            $pid = getmypid();
            file_put_contents($this->filename, $pid);
        }
       
    }

    public function __destruct() {

        if(!$this->already_running && file_exists($this->filename) && is_writeable($this->filename)) {
            unlink($this->filename);
        }
   
    }
   
}

?>
