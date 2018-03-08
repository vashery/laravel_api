<?php

namespace uhin\laravel_api\Workers;


class BaseRabbitWorker extends BaseWorker
{


    public  function start($pidName) {


        $pid_path = storage_path('pids/'.$pidName.'/');


        if (!file_exists($pid_path)) {
            mkdir($pid_path, 0777, true);
        }

        //delete any unlocked pid files
        //array_map('unlink', glob($pid_path.'*.pid'));

        $lock_file = null;
        chdir($pid_path);
        for ($i= 0; $i < $this->numberOfWorkers; $i++) {
            $lock_file = fopen("{$pidName}{$i}.pid", 'c');
            $got_lock = flock($lock_file, LOCK_EX | LOCK_NB, $wouldblock);


            if ($lock_file === false || (!$got_lock && !$wouldblock)) {
                // Unexpected error
            } else if (!$got_lock && $wouldblock) {
                // Trying next
                echo 'locked';
            } else if ($got_lock) {
                //We got a lock, time to create pid file
                $file = app_path()."/Workers/".$pidName.".php";

                $cmd = "php -r 'include ";
                $cmd .= '"'.$file.'"; ';
                $cmd .= '$class = new '.static::class;
                $cmd .= '; $class->run("'.$pidName.'")';
                $cmd .=";'";

                $command = 'nohup '.$cmd.' > '.storage_path('logs/').'test.log 2>&1 & echo $!';
                exec($command ,$op);
                $pid = (int)$op[0];

                ftruncate($lock_file, 0);
                fwrite($lock_file, $pid . "\n");
            }

            if ($i == $this->numberOfWorkers - 1) {
                exit;
            }

        }

    }

    public  function run($name) {
        sleep(1);

        $pid_path = storage_path('pids/'.$name.'/');

        foreach(glob($pid_path.'*.pid') as $filename)
        {
            $lines = file($filename);

            $found = false;
            $pid = strval(getmypid());
            foreach($lines as $line)
            {

                $line = trim($line);

                if(strpos($line, (string)$pid) !== false)
                {

                    $found = true;
                    $file = $filename;

                }
            }

            if($found) {
                //lock the file
                $lock_file = fopen($file, 'c');
                $got_lock = flock($lock_file, LOCK_EX | LOCK_NB, $wouldblock);

                if (!$got_lock) {
                    echo "NO LOCK";
                    echo strval($wouldblock);
                    fclose($lock_file);
                    exit();
                } else {
                    echo "LOCKED!";
                    echo strval($wouldblock);
                }

            }
            else
            {
                //couldn't find my lock, so I exit.
                exit();
            }

        }


    }


}