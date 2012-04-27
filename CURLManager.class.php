<?
/* CURLManager.class.php - Class for simplifying CURL mutliplexing
 * Copyright (C) 2007 Erik Osterman <e@osterman.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/* File Authors:
 *   Erik Osterman <e@osterman.com>
 */


class CURLManager
{
  protected $maxConcurrent;
  protected $throttle;
  protected $queue;
  protected $requests;
  protected $mh;

  public function __construct($maxConcurrent, $throttle = 0.0100)
  {
    $this->maxConcurrent = $maxConcurrent;
    $this->throttle = $throttle;
    $this->mh = curl_multi_init();
    $this->requests = Array();
    $this->queue = Array();
  }

  public function __destruct()
  {
    unset($this->maxConcurrent);
    unset($this->throttle);
    unset($this->queue);
    unset($this->requests);
    unset($this->mh);
  }

  public function push($conn)
  {
    $this->queue[] = $conn;
  }

  public function shuffleQueue()
  {
    shuffle($this->queue);
  }

  public function requestBegin($conn)
  {
    if(is_resource($conn) === false)
      throw new Exception("Invalid resource");
    curl_multi_add_handle ($this->mh, $conn);
    $this->requests[] = $conn;
  }

  public function hydrate()
  {
    // Load conns into queue
  }

  public function requestCompleted($conn)
  {
    $result = curl_multi_getcontent($conn);
    curl_multi_remove_handle($this->mh, $conn);
    curl_close($conn);
    return $result;
  }

  public function loopOnce()
  {
    while(count($this->requests) <= $this->maxConcurrent )
    {
      if(count($this->queue) <= 1)
      {
        $this->hydrate();
        if(empty($this->queue))
          break;
      }

      $conn = array_shift($this->queue);
      $this->requestBegin($conn);
    }
    if (curl_multi_select($this->mh, $this->throttle) != -1) 
      $n = curl_multi_exec($this->mh, $active);

    $results = 0;
    while($info = curl_multi_info_read($this->mh, $results))
    {
      //var_dump($info);
      $handle = $info['handle'];
      //if($info['result'] == 7) continue;  // if this is enabled, the requests linger on forever
      foreach ($this->requests as $i => $conn) 
      {
        if($handle == $conn)
        {
          unset($this->requests[$i]);
          $this->requestCompleted($conn);
        }
      }
    }
  }

  public function dispatch()
  {
    while(!empty($this->queue) || !empty($this->requests))
    {
      $this->loopOnce();
      usleep($this->throttle * 1000000);
    }
  }
}

?>
