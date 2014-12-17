<?php

use Symfony\Component\HttpFoundation\Request;

require_once __DIR__.'/../vendor/autoload.php'; 

$app = new Silex\Application(); 
$app['debug'] = true;

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));
$twig = $app['twig'];
$twig->addExtension(new \Entea\Twig\Extension\AssetExtension($app));
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

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

$app->match('/', function(Request $request) use ($app) {
    if ($request->isMethod('POST')) {
        $sentences = $request->request->get('sentences');
        $doc = $request->request->get('doc');
        
        $extractList = new SimpleXMLElement(file_get_contents(__DIR__.'/ccl/extract/extracts.xml'));
        $newExtract = $extractList->addChild('extract');
        $newExtract->addAttribute('doc', $doc);
        $newExtract->addAttribute('sentences', $sentences);

        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($newExtract->asXML());

        file_put_contents(__DIR__.'/ccl/extract/extracts.xml', $dom->saveXML());
    }

    $text = array();

    $chunkList = new SimpleXMLElement(file_get_contents(__DIR__.'/ccl/text1.xml'));
    foreach ($chunkList as $paragraph) {
        foreach ($paragraph as $sentence) {
            foreach ($sentence as $tokUp) {
                $sentence = array();

                foreach ($tokUp as $tok) {
                    $orth = (string)$tok->orth;
                    $sentence[] = $orth;
                }

                $text[] = implode(' ', $sentence);
            }
        }
    }

    return $app['twig']->render('index.html.twig', array('text' => $text, 'extractSize' => floor(0.2*count($text))));
})
->bind('homepage'); 

$app->run(); 
