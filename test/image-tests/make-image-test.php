#!/usr/bin/php
<?php

/*  PEL: PHP Exif Library.  A library with support for reading and
 *  writing all Exif headers in JPEG and TIFF images using PHP.
 *
 *  Copyright (C) 2005, 2006  Martin Geisler.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program in the file COPYING; if not, write to the
 *  Free Software Foundation, Inc., 51 Franklin St, Fifth Floor,
 *  Boston, MA 02110-1301 USA
 */

/* $Id$ */


/* This meta-program will generate a PHP script with unit tests for
 * testing the image supplied on the command line.  It works by
 * loading the image, and traversing it, outputting test code at each
 * step which will verify that a future parse of the image gives the
 * same results.
 */

if (count($argv) != 2)
  exit("Usage: $argv[0] <image>\n");

$basename = substr($argv[1], 0, -strlen(strrchr($argv[1], '.')));
$image_filename = $argv[1];
$thumb_filename = $basename . '-thumb.jpg';
$test_filename  = $basename . '.php';
$test_name      = str_replace('-', '_', $basename);


$indent = 0;

function println(/* fmt, args... */) {
  global $indent;
  $args = func_get_args();
  $str = array_shift($args);
  vprintf(str_repeat('  ', $indent) . $str . "\n", $args);
}


function quote($str) {
  return str_replace(array('\\', '\''), array('\\\\', '\\\''), $str);
}


function entryToTest($name, PelEntry $entry) {
  println('$this->assertIsA(%s, \'%s\');',
          $name, get_class($entry));

  println('$this->assertEqual(%s->getValue(), %s);',
          $name, var_export($entry->getValue(), true));

  println('$this->assertEqual(%s->getText(), \'%s\');',
          $name, quote($entry->getText()));
}


function ifdToTest($name, $number, PelIfd $ifd) {
  println();
  println('/* Start of IDF %s%d. */', $name, $number);

  $entries = $ifd->getEntries();
  println('$this->assertEqual(count(%s%d->getEntries()), %d);',
          $name, $number, count($entries));

  foreach ($entries as $tag => $entry) {
    println();
    println('$entry = %s%d->getEntry(%d); // %s',
            $name, $number, $tag,
            PelTag::getName($ifd->getType(), $tag));
    entryToTest('$entry', $entry);
  }

  println();
  println('/* Sub IFDs of %s%d. */', $name, $number);

  $sub_ifds = $ifd->getSubIfds();
  println('$this->assertEqual(count(%s%d->getSubIfds()), %d);',
          $name, $number, count($sub_ifds));

  $n = 0;
  $sub_name = $name . $number . '_';
  foreach ($sub_ifds as $type => $sub_ifd) {
    println('%s%d = %s%d->getSubIfd(%d); // IFD %s',
            $sub_name, $n, $name, $number, $type, $sub_ifd->getName());
    println('$this->assertIsA(%s%d, \'PelIfd\');', $sub_name, $n);
    ifdToTest($sub_name, $n, $sub_ifd);
    $n++;
  }

  println();

  if (strlen($ifd->getThumbnailData()) > 0) {
    println('$thumb_data = file_get_contents(dirname(__FILE__) .');
    println('                                \'/%s\');',
            $GLOBALS['thumb_filename']);
    println('$this->assertEqual(%s%d->getThumbnailData(), $thumb_data);',
            $name, $number);
  } else {
    println('$this->assertEqual(%s%d->getThumbnailData(), \'\');',
            $name, $number);
  }

  println();
  println('/* Next IFD. */');

  $next = $ifd->getNextIfd();
  println('%s%d = %s%d->getNextIfd();', $name, $number+1, $name, $number);

  if ($next instanceof PelIfd) {
    println('$this->assertIsA(%s%d, \'PelIfd\');', $name, $number+1);
    println('/* End of IFD %s%d. */', $name, $number);

    ifdToTest($name, $number+1, $next);
  } else {
    println('$this->assertNull(%s%d);', $name, $number+1);
    println('/* End of IFD %s%d. */', $name, $number);
  }

}


function tiffToTest($name, PelTiff $tiff) {
  println();
  println('/* The first IFD. */');
  println('$ifd0 = %s->getIfd();', $name);
  $ifd = $tiff->getIfd();
  if ($ifd instanceof PelIfd) {
    println('$this->assertIsA($ifd0, \'PelIfd\');');
    ifdToTest('$ifd', 0, $ifd);
  } else {
    println('$this->assertNull($ifd0);');
  }
}


function jpegContentToTest($name, PelJpegContent $content) {
  if ($content instanceof PelExif) {
    println('$this->assertIsA(%s, \'PelExif\');', $name);
    $tiff = $content->getTiff();
    println();
    println('$tiff = %s->getTiff();', $name);
    if ($tiff instanceof PelTiff) {
      println('$this->assertIsA($tiff, \'PelTiff\');');
      tiffToTest('$tiff', $tiff);
    }
  }
}


function jpegToTest($name, PelJpeg $jpeg) {
  $exif = $jpeg->getExif();
  println('$exif = %s->getExif();', $name);
  if ($exif == null) {
    println('$this->assertNull($exif);');
  } else {
    jpegContentToTest('$exif', $exif);
  }
}


/* All output is buffered so that we can dump it in $test_filename at
 * the end. */
ob_start();

println('<?php

/*  PEL: PHP Exif Library.  A library with support for reading and
 *  writing all Exif headers in JPEG and TIFF images using PHP.
 *
 *  Copyright (C) 2005, 2006  Martin Geisler.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program in the file COPYING; if not, write to the
 *  Free Software Foundation, Inc., 51 Franklin St, Fifth Floor,
 *  Boston, MA 02110-1301 USA
 */

/* $Id$ */

/* Autogenerated by the make-image-test.php script */


class %s extends UnitTestCase {

  function __construct() {
    require_once(\'../PelJpeg.php\');
    parent::__construct(\'PEL %s Tests\');
  }

  function testRead() {
    Pel::clearExceptions();
    Pel::setStrictParsing(false);
    $jpeg = new PelJpeg(dirname(__FILE__) . \'/%s\');
', $test_name, $image_filename, $image_filename);

require_once('../../PelJpeg.php');
$jpeg = new PelJpeg($image_filename);

$indent = 2;
jpegToTest('$jpeg', $jpeg);

println();

$exceptions = Pel::getExceptions();

if (count($exceptions) == 0) {
  println('$this->assertTrue(count(Pel::getExceptions()) == 0);');
} else {
  println('$exceptions = Pel::getExceptions();');
  for ($i = 0; $i < count($exceptions); $i++) {
    println('$this->assertIsA($exceptions[%d], \'%s\');',
            $i, get_class($exceptions[$i]));

    println('$this->assertEqual($exceptions[%d]->getMessage(),', $i);
    println('                   \'%s\');',
            quote($exceptions[$i]->getMessage()));
  }
}

println('
  }
}

?>');

/* The test case is finished -- now dump the output as a PHP file. */
file_put_contents($test_filename, ob_get_clean());