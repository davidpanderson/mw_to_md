#! /usr/bin/env php
<?php
// Copyright 2024 David P. Anderson

// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
// http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.

// script to convert a Mediawiki wiki to Github markdown
// See https://github.com/davidpanderson/mw_to_md/wiki

// Set the following 3 vars based on your setup:

$mw_base_url = 'https://boinc.berkeley.edu/w';
    // URL of the MW wiki
$root_file = 'User_manual';
    // name of root MW file
$img_base_url = 'https://github.com/BOINC/boinc/wiki/images';
    // base URL for images in the MD wiki

// fetch a MW file
//
function fetch_mw($name) {
    global $mw_base_url;
    $url = "$mw_base_url/?title=$name&action=raw";
    return copy($url, "mw/$name");
}

function substr2($s, $n1, $n2) {
    return substr($s, $n1, $n2-$n1);
}

// list of image files referenced by converted pages
//
$img_files = [];

// convert the given MW file.
// Write the result to md/fname.md
// Return a list of image filenames
//
function mw_to_md($fname) {
    global $img_files, $img_base_url;
    $f = fopen("mw/$fname", 'r');
    $x = '';
    $files = [];
    while (1) {
        $s = fgets($f);
        if (!$s) break;
        if (strstr($s, 'REDIRECT')) {
            die("got redirect\n");
        }
        if (substr($s, -1, 1) == "\n") {
            $s = substr($s, 0, -1);
        }

        // use a placeholder, change it later
        $s = str_replace("'''", '&**&', $s);

        // handle start-of-line constructs
        //
        if (substr($s, 0, 1) == '_') continue;
        if (substr($s, 0, 1) == '<') continue;
        if (substr($s, 0, 1) == '=') {
            $s = str_replace('=', '#', $s);
            $s = trim($s);
            while (substr($s, -1) == '#') {
                $s = substr($s, 0, -1);
            }
            if (strstr($s, '####')) {
                $s = str_replace('####', '#### ', $s);
            } else if (strstr($s, '###')) {
                $s = str_replace('###', '### ', $s);
            } else if (strstr($s, '##')) {
                $s = str_replace('##', '## ', $s);
            } else if (strstr($s, '#')) {
                $s = str_replace('#', '# ', $s);
            }
        } else if (substr($s, 0, 1) == '#') {
            $s = sprintf('1. %s', substr($s, 1));
        } else if (substr($s, 0, 1) == '*') {
            $t = '';
            while (substr($s, 1, 1) == '*') {
                $t .= '  ';
                $s = substr($s, 1);
            }
            $s = $t.$s;
        } else if (substr($s, 0, 1) == ':') {
            $x .= '&nbsp;&nbsp;&nbsp;';
            $s = substr($s, 1);
        } else if (substr($s, 0, 1) == ' ') {
            $s = trim($s);
            $x .= "    $s\n";
            continue;
        } else if (substr($s, 0, 1) == ';') {
            $n1 = strpos($s, ':');
            if ($n1 === false) {
                $term = trim(substr($s, 1));
                $s = sprintf("<dl><dt>\n\n%s\n</dt>\n</dl>\n",
                    $term
                );
            } else {
                $term = trim(substr2($s, 1, $n1));
                $def = trim(substr($s, $n1+1));
                $s = sprintf("<dl><dt>\n\n%s\n</dt>\n<dd>\n%s\n</dd>\n</dl>\n",
                    $term, $def
                );
            }

        // tables
        //
        } else if (substr($s, 0, 2) == '{|') {
            $ncols = 0;
            $did_header = false;
            $x .= "\n";
            continue;
        } else if (substr($s, 0, 2) == '|}') {
            if ($ncols) {
                $x .= '|';
            }
            $x .= "\n";
            continue;
        } else if (substr($s, 0, 1) == '!') {
            $s = trim(substr($s, 1));
            $x .= "| $s ";
            $ncols++;
            continue;
        } else if (substr($s, 0, 2) == '|-') {
            if ($ncols) {
                $x .= "|\n";
                if (!$did_header) {
                    $x .= "| ";
                    for ($i=0; $i<$ncols; $i++) {
                        $x .= ' --- |';
                    }
                    $x .= "\n";
                    $did_header = true;
                }
                $ncols = 0;
            }
            continue;
        } else if (substr($s, 0, 1) == '|') {
            $s = trim(substr($s, 1));
            $x .= "| $s ";
            $ncols++;
            continue;
        }

        // handle other constructs
        //
        // $out is the output, $s is text left to process
        //

        $out = '';
        while (1) {
            // handle images
            // can be like
            // [[Image: 7.6.View_menu.jpg|200px|right|The BOINC Manager menu.]]
            // MD notation is [[images/pipeline.svg|width=690px]]
            //
            $n1 = strpos($s, '[[Image:');
            if ($n1 !== false) {
                $n2 = strpos($s, ']]', $n1);
                if ($n2 === false) die("bad line 1 $s\n");
                $t = substr2($s, $n1+8, $n2);
                $u = explode('|', $t);
                $img_file = trim($u[0]);
                $img_files[] = "cp all_images/$img_file images/";
                if (count($u) == 1) {
                    $out .= sprintf('%s<img src="%s/%s">%s',
                        "\n", $img_base_url, $img_file, "\n"
                    );
                } else {
                    $n = count($u);
                    $px = false;
                    for ($i=1; $i<$n-1; $i++) {
                        if (strstr($u[$i], 'px')) {
                            $px = $u[$i];
                        }
                    }
                    $alt = $u[$n-1];
                    if ($px) {
                        $out .= sprintf(
                            '%s<img src="%s/%s" width="%s" title="%s">%s',
                            "\n", $img_base_url, $img_file, $px, $alt, "\n"
                        );
                    } else {
                        $out .= sprintf(
                            '%s<img src="%s/%s" title="%s">%s',
                            "\n", $img_base_url, $img_file, $alt, "\n"
                        );
                    }
                }
                $s = substr($s, $n2+2);
                continue;
            }
            // handle links
            $n1 = strpos($s, '[');
            $n2 = strpos($s, '[[');
            if ($n1 !== false && ($n1 === $n2)) {
                // there's an [[, and it's first
                // Internal link
                //
                $n2 = strpos($s, ']]', $n1);
                if ($n2 === false) die("bad line 2 $s\n");
                $t = substr2($s, $n1+2, $n2);
                if (strstr($t, 'Category:')) {
                    $s = '';
                    break;
                }
                $u = explode('|', $t);
                switch (count($u)) {
                case 1:
                    $text = trim($u[0]);
                    $name = str_replace(' ', '_', $text);
                    break;
                case 2:
                    $text = trim($u[1]);
                    $name = str_replace(' ', '_', trim($u[0]));
                    break;
                default:
                    die("bad link 1 $t\n");
                }
                if (strstr($name, 'wikipedia')) die('no wikipedia links');
                if (strstr($name, 'http') === false && strstr($name, '#') === false) {
                    $files[] = $name;
                }
                if (strstr($text, '#') === false) {
                    $out .= sprintf('%s[%s](%s)',
                        substr($s, 0, $n1),
                        $text,
                        $name
                    );
                }
                $s = substr($s, $n2+2);
                continue;
            }
            if ($n1 !== false) {
                // external link
                //
                $n2 = strpos($s, ']', $n1);
                if ($n2 === false) {
                    die("bad line 3 $s\n");
                }
                $t = substr2($s, $n1+1, $n2);
                $n3 = strpos($s, ' ', $n1);
                if ($n3 === false) die("bad link 2 $t\n");
                $text = trim(substr2($s, $n3, $n2));
                $name = trim(substr2($s, $n1+1, $n3));
                if (strstr($name, 'http') === false) $files[] = $name;
                $out .= sprintf('%s[%s](%s)',
                    substr($s, 0, $n1),
                    $text,
                    $name
                );
                $s = substr($s, $n2+1);
                continue;
            }
            break;
        }
        $out = str_replace('&**&', '**', $out);
        $s = str_replace('&**&', '**', $s);

        // Github gags on '**<'; escape the <
        //
        $out = str_replace('**<', '**\<', $out);
        $s = str_replace('**<', '**\<', $s);
        $x .= "$out$s\n";
    }
    $fname = str_replace('_', ' ', $fname);
    file_put_contents("md/$fname.md", $x);
    return $files;
}

// convert the given MW file, and (recursively) the MW files it links to
//
function convert_recurse($fname) {
    echo "converting $fname\n";
    $ret = fetch_mw($fname);
    if ($ret === false) {
        echo "can't fetch $fname\n";
        return;
    }
    $files = mw_to_md($fname);
    echo "done with $fname\n";
    echo "converting files from $fname\n";
    foreach ($files as $file) {
        $file = ucfirst($file);
        if (!file_exists("mw/$file")) {
            convert_recurse($file);
        }
    }
    echo "done converting files from $fname\n";
}

convert_recurse($root_file);

// make a script to copy referenced image files from all_images/ to images/
//
$img_files = array_unique($img_files);
file_put_contents("copy_images", implode("\n", $img_files));

?>
