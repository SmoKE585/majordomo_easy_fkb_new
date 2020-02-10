<?php
    chdir(dirname(__FILE__) . '/../');
    include_once("./config.php");
    include_once("./lib/loader.php");
    include_once("./lib/threads.php");
    set_time_limit(0);

    // connecting to database
    $db = new mysql(DB_HOST, '', DB_USER, DB_PASSWORD, DB_NAME);
    include_once("./load_settings.php");

    include_once(DIR_MODULES . 'easy_fkb/easy_fkb.class.php');
    $easy_fkb = new easy_fkb();
    $easy_fkb->getConfig();
	
    $sleepTime = (int) $easy_fkb->config['TIME_AUTO_RELOAD'];

    if ($sleepTime == 0) {
        setGlobal('cycle_easy_fkb', 'stop');
        setGlobal('cycle_easy_fkb', '0');
        exit;
    }

    setGlobal('cycle_easy_fkb', '1');

    while(TRUE) {
        setGlobal((str_replace('.php', '', basename(__FILE__))) . 'Run', time(), 1);
		
        $easy_fkb->processCycle();
		
		if($easy_fkb->config['LOGS_ENABLED'] == 'on') debMes('Выполнено обновление данных из цикла...', 'easy_fkb');
		
        if (file_exists('./reboot') || IsSet($_GET['onetime'])) {
            $db->Disconnect();
            exit;
        }

        sleep($sleepTime);
    }

    DebMes("Unexpected close of cycle: " . basename(__FILE__));

