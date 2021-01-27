<?php

use App\Listeners\GenerateSitemap;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/** @var $container \Illuminate\Container\Container */
/** @var $events \TightenCo\Jigsaw\Events\EventBus */

/**
 * You can run custom code at different stages of the build process by
 * listening to the 'beforeBuild', 'afterCollections', and 'afterBuild' events.
 *
 * For example:
 *
 * $events->beforeBuild(function (Jigsaw $jigsaw) {
 *     // Your code here
 * });
 * 
 */



$noembed_replacements = [
    'youtube' => 'https://www.youtube.com/watch?v=',
    'vimeo' => 'https://vimeo.com/'
];

$emoji_replacements = json_decode(file_get_contents(__DIR__ . '/emojis.json'), true);

$block_types = ['success', 'info', 'warning', 'danger'];


$events->beforeBuild(function($jigsaw){


    $files = $jigsaw->getFilesystem()->files($jigsaw->getSourcePath().'/_strony');
    foreach ($files as $file) {
        $new_content = 
'---
extends: templates.page
section: content
---
' . $jigsaw->getFilesystem()->get($file);
        
        $jigsaw->getFilesystem()->putWithDirectories($file, $new_content);
    }
    


    $files = $jigsaw->getFilesystem()->files($jigsaw->getSourcePath().'/_aktualnosci');
    foreach ($files as $file) {
        $parser = new Mni\FrontYAML\Parser();

        $document = $parser->parse($jigsaw->getFilesystem()->get($file), false);

        $yaml = $document->getYAML();
        if (isset($yaml['data']) && strlen($yaml['data']) === 19) {

            $new_content = 
'---
data: \'' . $yaml['data'] . '\'
extends: templates.post
section: content
---
' . $document->getContent();

        } else {

            $new_content = 
'---
data: \'' . Date::now() . '\'
extends: templates.post
section: content
---
' . $document->getContent();

        }
        
        $jigsaw->getFilesystem()->putWithDirectories($file, $new_content);
    }




    $files = $jigsaw->getFilesystem()->files($jigsaw->getSourcePath().'/_poradniki');

    foreach ($files as $file) {

        $parser = new Mni\FrontYAML\Parser();

        $document = $parser->parse($jigsaw->getFilesystem()->get($file), false);

        $yaml = $document->getYAML();
        if (isset($yaml['data']) && strlen($yaml['data']) === 19) {

            $new_content = 
'---
data: \'' . $yaml['data'] . '\'
extends: templates.artl
section: content
---
' . $document->getContent();

        } else {

            $new_content = 
'---
data: \'' . Date::now() . '\'
extends: templates.artl
section: content
---
' . $document->getContent();

        }

        
        $jigsaw->getFilesystem()->putWithDirectories($file, $new_content);
    }




    $files = $jigsaw->getFilesystem()->files($jigsaw->getSourcePath().'/_krok_po_kroku');

    foreach ($files as $file) {

        $parser = new Mni\FrontYAML\Parser();

        $document = $parser->parse($jigsaw->getFilesystem()->get($file), false);

        $yaml = $document->getYAML();
        if (isset($yaml['kolejnosc']) && strlen($yaml['kolejnosc']) <= 3) {

            $new_content = 
'---
kolejnosc: ' . str_pad($yaml['kolejnosc'], 3) . '
extends: templates.step
section: content
---
' . $document->getContent();

        } else {

            $new_content = 
'---
kolejnosc: 0  
extends: templates.step
section: content
---
' . $document->getContent();

        }

        
        $jigsaw->getFilesystem()->putWithDirectories($file, $new_content);
    }



});


$events->afterBuild(GenerateSitemap::class);

$events->afterBuild(function($jigsaw) use ($emoji_replacements, $noembed_replacements, $block_types) {

    $files = $jigsaw->getFilesystem()->files($jigsaw->getSourcePath().'/_strony');
    foreach ($files as $file) {

        $new_content = substr($jigsaw->getFilesystem()->get($file), 49);
        
        $jigsaw->getFilesystem()->putWithDirectories($file, $new_content);
    }
    
    $files = array_merge(
        $jigsaw->getFilesystem()->files($jigsaw->getSourcePath().'/_poradniki'),
        $jigsaw->getFilesystem()->files($jigsaw->getSourcePath().'/_aktualnosci')
    );
    foreach ($files as $file) {
        $file_content = $jigsaw->getFilesystem()->get($file);
        $new_content = substr($file_content, 0, 32) . substr($file_content, 73);
        
        $jigsaw->getFilesystem()->putWithDirectories($file, $new_content);
    }

    $files = $jigsaw->getFilesystem()->files($jigsaw->getSourcePath().'/_krok_po_kroku');
    foreach ($files as $file) {
        $file_content = $jigsaw->getFilesystem()->get($file);
        $new_content = substr($file_content, 0, 19) . substr($file_content, 60);
        
        $jigsaw->getFilesystem()->putWithDirectories($file, $new_content);
    }

    $files = array_merge(
        $jigsaw->getFilesystem()->files($jigsaw->getDestinationPath().'/strony'),
        $jigsaw->getFilesystem()->files($jigsaw->getDestinationPath().'/poradniki'),
        $jigsaw->getFilesystem()->files($jigsaw->getDestinationPath().'/aktualnosci'),
        $jigsaw->getFilesystem()->files($jigsaw->getDestinationPath().'/krok-po-kroku')
    );
    $files[] = $jigsaw->getFilesystem()->getFile($jigsaw->getDestinationPath(), 'index.html');

    foreach ($files as $file) {

        $new_content = $jigsaw->getFilesystem()->get($file);
        

        
        /* Markdown embeds */

        $callables = [];
        $http = new Client(['base_uri' => 'http://noembed.com']);

        foreach ($noembed_replacements as $service => $prefix) {
            $callables["/{%$service +(.*?) *%}/"] = function (&$matches) use ($prefix, $http) {
                $resp = json_decode($http->get('/embed?url=' . urlencode($prefix . $matches[1]))
                    ->getBody()
                    ->getContents()
                );

                if (intval($resp->width ?? 0) && intval($resp->height ?? 0)) {
                    $ratio = $resp->height / $resp->width * 100;
                    return ('<div class="ratio-iframe" style="padding-bottom: ' . $ratio . '%">') . ($resp->html ?? '') . '</div>';
                }

                return $resp->html ?? '';
            };
        }
        
        $new_content = preg_replace_callback_array(
            $callables,
            $new_content
        );



        /* Markdown emojis */

        foreach ($emoji_replacements as $code => $emoji) {
            $new_content = str_replace(":$code:", $emoji, $new_content);
        }



        /* Markdown blocks */

        foreach ($block_types as $type) {
            $new_content = preg_replace('/(<p>:::|:::)' . $type . '(.*?)(\R|<\/p>\R)([\s\S]*?)(\R|\R<p>)(:::<\/p>|:::)/u', '<aside class="alert alert-' . $type  . '">$4</aside>', $new_content);
        }


        
        /* Markdown spoiler */

        $new_content = preg_replace('/(<p>:::|:::)spoiler (.*?)(\R|<\/p>\R)([\s\S]*?)(\R|\R<p>)(:::<\/p>|:::)/u', '<details><summary>$2</summary><p>$4</p></details>', $new_content);
        $new_content = preg_replace('/(<p>:::|:::)spoiler(\R|<\/p>\R)([\s\S]*?)(\R|\R<p>)(:::<\/p>|:::)/u', '<details><p>$3</p></details>', $new_content);

        

        $jigsaw->getFilesystem()->putWithDirectories($file, $new_content);
    }


    /* TOC */

    $poradniki = $jigsaw->getFilesystem()->files($jigsaw->getDestinationPath().'/poradniki');

    foreach ($poradniki as $file) {
        $new_content = $jigsaw->getFilesystem()->get($file);


        preg_match_all( '|<h([^>]+)>(.*)</h[^>]+>|iU', $new_content, $headings );


        $processed_headings = [];

        $new_content = preg_replace_callback('|<h([^>]+)>(.*)</h([^>]+)>|iU', function (&$matches) use (&$processed_headings) {
                if (in_array($matches[1][0], ['1', '2', '3'])) {
                    $processed_headings[] = [
                        'level' => $matches[1][0],
                        'text' => $matches[2],
                        'slug' => $slug = Str::slug($matches[2])
                    ];
                    return "<h$matches[1] id=\"$slug\">$matches[2]</h$matches[3]>";
                }
                return $matches[0];
            },
            $new_content
        );

        if ($processed_headings) {

            $toc = '<aside class="bg-white dark:bg-gray-800 shadow rounded-lg px-4 py-1 lg:py-0 toc my-8 lg:my-4"><p class="text-2xl leading-tight px-3 font-extrabold text-indigo-800 dark:text-indigo-200">Spis treści</p><nav><ul class="list-none pl-0">';

            foreach ($processed_headings as $h) {
                $toc .= '<li><a class="block leading-tight border-b-0 text-gray-700 dark:text-gray-300 hover:bg-indigo-100 dark:hover:bg-indigo-800 hover:text-indigo-800 dark:hover:text-white p-4 font-medium rounded-md" style="padding-left: ' . $h['level'] . 'rem" href="#' . $h['slug'] . '">' . $h['text'] . '</a></li>';
            }
    
            $toc .= '</ul></nav></aside>';
    
            $new_content = preg_replace('/<\/h1>/iu', "</h1>\n$toc", $new_content, 1);
    
            $jigsaw->getFilesystem()->putWithDirectories($file, $new_content);
        }
        
    }

    $kroki = $jigsaw->getFilesystem()->files($jigsaw->getDestinationPath().'/krok-po-kroku');

    foreach ($kroki as $file) {

        if (Str::endsWith($file->getPath(), '/krok-po-kroku')) continue;

        $new_content = $jigsaw->getFilesystem()->get($file);


        preg_match_all( '|<h([^>]+)>(.*)</h[^>]+>|iU', $new_content, $headings );


        $processed_headings = [];

        $new_content = preg_replace_callback('|<h([^>]+)>(.*)</h([^>]+)>|iU', function (&$matches) use (&$processed_headings) {
                if (in_array($matches[1][0], ['2', '3'])) {
                    $processed_headings[] = [
                        'level' => $matches[1][0],
                        'text' => $matches[2],
                        'slug' => $slug = Str::slug($matches[2])
                    ];
                    return "<h$matches[1] id=\"$slug\">$matches[2]</h$matches[3]>";
                }
                return $matches[0];
            },
            $new_content
        );
        $toc = '<aside class="bg-white dark:bg-gray-800 shadow rounded-lg px-4 py-1 lg:py-0 toc my-8 lg:my-4"><p class="text-2xl leading-tight px-3 font-extrabold text-indigo-800 dark:text-indigo-200">Spis treści</p><nav><ul class="list-none pl-0">';


        foreach($jigsaw->getCollection('krok_po_kroku') as $krok) {

            $toc .= '<li><a class="block leading-tight border-b-0 text-gray-700 dark:text-gray-300 hover:bg-indigo-100 dark:hover:bg-indigo-800 hover:text-indigo-800 dark:hover:text-white p-4 font-medium rounded-md" href="' . $krok->getUrl() . '">' . $krok->title() . '</a></li>';

            if (str_contains($file->getPath(), $krok->getFilename()) && $processed_headings) {
                $toc .= '<ul class="list-none pl-0">';
                foreach ($processed_headings as $h) {
                    $toc .= '<li><a class="block leading-tight border-b-0 text-gray-700 dark:text-gray-300 hover:bg-indigo-100 dark:hover:bg-indigo-800 hover:text-indigo-800 dark:hover:text-white p-4 font-medium rounded-md" style="padding-left: ' . $h['level'] . 'rem" href="#' . $h['slug'] . '">' . $h['text'] . '</a></li>';
                }
                $toc .= '</ul>';
            }

        } 

        $toc .= '</ul></nav></aside>';
    
        $new_content = preg_replace('/<\/h1>/iu', "</h1>\n$toc", $new_content, 1);

        $jigsaw->getFilesystem()->putWithDirectories($file, $new_content);
        
    }
});

