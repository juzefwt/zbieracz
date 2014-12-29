<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__.'/../vendor/autoload.php'; 

$app = new Silex\Application(); 
$app['debug'] = true;

$app->register(new Silex\Provider\SessionServiceProvider());
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
        $author = $request->cookies->get('who');

        $xml = file_get_contents(__DIR__.'/ccl/extracts.xml');

        if ($xml == '') {
            $xml = '<extracts></extracts>';
        }

        $extractList = new SimpleXMLElement($xml);
        $newExtract = $extractList->addChild('extract');
        $newExtract->addAttribute('doc', $doc);
        $newExtract->addAttribute('author', $author);
        $newExtract->addAttribute('sentences', $sentences);

        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($extractList->asXML());

        file_put_contents(__DIR__.'/ccl/extracts.xml', $dom->saveXML());

        $app['session']->getFlashBag()->add('message', 'Streszczenie zostało zapisane.');

        return $app->redirect($app["url_generator"]->generate("homepage"));
    }

    if (!$request->cookies->has('who')) {
        $who = sha1(time() + rand(1, 100));
        $cookie = new Cookie("who", $who, time() + 3600 * 24 * 365);
        $response = Response::create('', 302, array("Location" => $app['url_generator']->generate('homepage')));
        $response->headers->setCookie($cookie);

        return $response;
    }

    $text = array();
    $sentencesNumber = 0;

    $filesAvailable = array();
    if ($handle = opendir(__DIR__.'/ccl/')) {

        while (false !== ($entry = readdir($handle))) {
            if (!in_array($entry, array(".", "..", "extracts.xml"))) {
                $fileName = str_replace('.xml', '', $entry);
                $filesAvailable[$fileName] = 0;
            }
        }

        closedir($handle);

        $xml = file_get_contents(__DIR__.'/ccl/extracts.xml');

        if ($xml) {
            $extracts = new SimpleXMLElement($xml);
            foreach ($extracts as $extract) {
                $docName = (string)$extract['doc'];
                $filesAvailable[$docName]++;
            }
        }

        asort($filesAvailable);
        reset($filesAvailable);
        $docName = key($filesAvailable);
    } else {
        throw new \RuntimeException('Brak dostępu do streszczanych dokumentów');
    }
    $chunkList = new SimpleXMLElement(file_get_contents(__DIR__.'/ccl/'.$docName.'.xml'));
    foreach ($chunkList as $paragraph) {
        foreach ($paragraph as $sentence) {
            $pgp = array();
            foreach ($sentence as $tokUp) {
                $sentence = array();

                foreach ($tokUp as $tok) {
                    $orth = (string)$tok->orth;
                    $sentence[] = $orth;
                }

                $pgp[] = implode(' ', $sentence);
                $sentencesNumber++;
            }

            $text[] = $pgp;
        }
    }

    return $app['twig']->render('index.html.twig', array(
        'text' => $text,
        'docName' => $docName,
        'sentencesNumber' => $sentencesNumber,
        'extractSize' => floor(0.2*$sentencesNumber))
    );
})
->bind('homepage'); 

$app->run(); 
