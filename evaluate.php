<?php

$extractList = new SimpleXMLElement(file_get_contents(__DIR__.'/web/ccl/extracts.xml'));
$modelExtracts = array();

foreach ($extractList as $extract) {
    $docName = (string)$extract['doc'];
    $modelSentences = explode(',', (string)$extract['sentences']);

    $processedFile = __DIR__.'/web/txt/'.$docName;
    
    if (empty($modelSentences) || !file_exists($processedFile)) {
        continue;
    }

    if (!isset($modelExtracts[$docName])) {
        $modelExtracts[$docName] = array();
    }
    $modelExtracts[$docName][] = $modelSentences;
}

foreach ($modelExtracts as $docName => $models) {
    $processedFile = __DIR__.'/web/txt/'.$docName;
    $oldmask = umask(0);
    $workingDirPath = '/tmp/xtr';

    if (file_exists($workingDirPath)) {
        rrmdir($workingDirPath.'/TEXT');
    }

    $clusterDirPath = $workingDirPath.'/TEXT';

    if (!mkdir($clusterDirPath, 0777)) {
        throw new RuntimeException('Cannot create cluster directory');
    }

    umask($oldmask);

    $scriptPath = __DIR__.'/../xtr/process.php';

    file_put_contents($clusterDirPath.'/TEXT1.txt', file_get_contents($processedFile));

    $cmd = sprintf('php %s %s', $scriptPath, $clusterDirPath);

    echo shell_exec($cmd);

    $docsentPath = $clusterDirPath.'/TEXT_CLUSTER/docsent/TEXT1.docsent';
    $docsentXml = new SimpleXMLElement(file_get_contents($docsentPath));

    $extractPath = $clusterDirPath.'/TEXT_CLUSTER/TEXT_CLUSTER.extract';
    $extractXml = new SimpleXMLElement(file_get_contents($extractPath));
    
    $systemExtract = array();
    $modelExtracts = array();

    foreach ($docsentXml->BODY->TEXT->children() as $s) {
        foreach ($extractXml as $sentence) {
            if ((int)$s['SNO'] == (int)$sentence['SNO']) {
                $systemExtract[] = (string) $s;
            }
        }

        foreach ($models as $i => $model) {
            foreach ($model as $modelSentenceIndex) {
                if ((int)$s['SNO'] == $modelSentenceIndex) {
                    $modelExtracts[$i][] = (string) $s;
                }
            }
        }
    }

    $evalPath = $clusterDirPath.'/TEXT_CLUSTER/eval';

    if (!mkdir($evalPath, 0777)) {
        throw new RuntimeException('Cannot create eval directory');
    }

    file_put_contents($evalPath.'/system.txt', implode(PHP_EOL, $systemExtract));

    $modelPaths = array();
    foreach ($modelExtracts as $i => $model) {
        file_put_contents($evalPath.'/model'.$i.'.txt', implode(PHP_EOL, $model));
        $modelPaths[] = $evalPath.'/model'.$i.'.txt';
    }

    $evalResultPath = __DIR__.'/web/eval/'.$docName;

    echo shell_exec('perl /usr/local/share/mead/bin/evaluation/rouge/rouge.pl '.$evalPath.'/system.txt '.implode(' ', $modelPaths));
    echo shell_exec('cd /usr/local/share/mead/rouge/kraken && perl /usr/local/share/mead/rouge/ROUGE-1.5.5.pl -e "/usr/local/share/mead/rouge/data" -c 95 -r 1000 -n 2 -m -a -l 100 -x /usr/local/share/mead/rouge/kraken/auto_temp.xml > '.$evalResultPath);
}

function rrmdir($dir) { 
    if (is_dir($dir)) { 
      $objects = scandir($dir); 
      foreach ($objects as $object) { 
        if ($object != "." && $object != "..") { 
          if (filetype($dir."/".$object) == "dir") rrmdir($dir."/".$object); else unlink($dir."/".$object); 
        } 
      } 
      reset($objects); 
      rmdir($dir); 
    } 
} 