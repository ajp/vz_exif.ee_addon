<?php

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
    'pi_name'        => 'VZ Exif',
    'pi_version'     => '1.0.10',
    'pi_author'      => 'Eli Van Zoeren',
    'pi_author_url'  => 'http://elivz.com/',
    'pi_description' => 'Extract the Exif information from an image',
    'pi_usage'       => Vz_exif::usage()
);

/**
 * @package         ExpressionEngine
 * @category        Plugin
 * @author          Eli Van Zoeren
 * @copyright       Copyright (c) 2014, Eli Van Zoeren
 * @link            https://github.com/elivz/vz_exif.ee_addon
 * @license         http://creativecommons.org/licenses/by-sa/3.0/ Attribution-Share Alike 3.0 Unported/
 */

class Vz_exif {

    public $return_data;

    /*
     * Tag pair: {exp:vz_exif}Template data{/exp:vz_exif}
     */
    public function __construct()
    {
        $this->return_data = $this->exif();
    }

    public function exif()
    {
        $tagdata = ee()->TMPL->tagdata;

        // Get all the data
        $data = array(array(
            'size' => $this->get_exif('FileSize'),
            'height' => $this->get_exif('Height'),
            'width' => $this->get_exif('Width'),
            'date' => $this->get_exif('DateTime'),
            'make' => $this->get_exif('Make'),
            'model' => $this->get_exif('Model'),
            'focal_length' => $this->get_exif('FocalLength'),
            'focal_length_equiv' => $this->get_exif('FocalLengthIn35mmFilm'),
            'aperture' => $this->get_exif('ApertureFNumber'),
            'shutter' => $this->get_exif('ExposureTime'),
            'iso' => $this->get_exif('ISOSpeedRatings'),
            'software' => $this->get_exif('Software'),
            'flash' => $this->get_exif('Flash'),
            'latitude' => $this->get_exif('GPSLatitude'),
            'longitude' => $this->get_exif('GPSLongitude')
        ));

        // Run the conditional statements
        $tagdata = ee()->functions->prep_conditionals($tagdata, $data[0]);

        // Replace the tags with their values
        return ee()->TMPL->parse_variables($tagdata, $data);
    }

    /*
    * Catch any other calls and use the method name called`
    * as the EXIF value to retrieve
    */
    public function size() {
        return $this->get_exif('FileSize');
    }

    public function height() {
        return $this->get_exif('Height');
    }

    public function width() {
        return $this->get_exif('Width');
    }

    public function date() {
        return $this->get_exif('DateTime');
    }

    public function make() {
        return $this->get_exif('Make');
    }

    public function model() {
        return $this->get_exif('Model');
    }

    public function focal_length() {
        return $this->get_exif('FocalLength');
    }

    public function focal_length_equiv() {
        return $this->get_exif('FocalLengthIn35mmFilm');
    }

    public function aperture() {
        return $this->get_exif('ApertureFNumber');
    }

    public function shutter() {
        return $this->get_exif('ExposureTime');
    }

    public function iso() {
        return $this->get_exif('ISOSpeedRatings');
    }

    public function software() {
        return $this->get_exif('Software');
    }

    public function flash() {
        return $this->get_exif('Flash');
    }

    public function latitude() {
        return $this->get_exif('GPSLatitude');
    }

    public function longitude() {
        return $this->get_exif('GPSLongitude');
    }


    /*
     * This is the heart of the plugin.
     * Get the exif data from the image
     * and return in in an array
     */
    private function get_exif($tag)
    {
        ee()->load->helper('string');

        $image = ee()->TMPL->fetch_param('image');
        $root = ee()->TMPL->fetch_param('path') ? ee()->TMPL->fetch_param('path') : ee()->config->item('vz_exif_root');

        // Initialize the cache
        $cache =& ee()->session->cache['vz_exif'][$root.$image];

        // Only get the Exif data once per page load
        if (!isset($cache))
        {
            $image = str_ireplace('http://'.$_SERVER['HTTP_HOST'], '', $image);
            $file = '';

            if ($root)
            {
                // Add the path to the image file to get the full path
                $file = reduce_double_slashes($root.$image);
            }
            elseif (strncmp($image, '/', 1) == 0)
            {
                // The image url is relative to the web root
                $file = reduce_double_slashes(FCPATH.'/'.$image);
            }

            // Get the data from the file
            if (@exif_imagetype($file) != IMAGETYPE_JPEG) return '<!-- The file "'.$file.'" could not be found or was not in jpeg format -->';
            $cache = exif_read_data($file);
        }

        $exif = $cache;

        // Get the value we need from the array
        switch ($tag) {
            case 'FileSize':
                return isset($exif[$tag]) ? round($exif[$tag] / 1024) : '';
            case 'Height': case 'Width': case 'ApertureFNumber':
                return isset($exif['COMPUTED'][$tag]) ? $exif['COMPUTED'][$tag] : '';
            case 'Make': case 'Model':
                return isset($exif[$tag]) ? ucwords(strtolower($exif[$tag])) : '';
            case 'FocalLength':
                $length = '';
                if (isset($exif[$tag]))
                {
                    $length = $exif[$tag];
                    if (strpos($exif[$tag], '/'))
                    {
                        $parts = explode('/', $length);
                        if (count($parts) == 2)
                        {
                            $length = $parts[0] / $parts[1];
                        }
                    }
                }
                return $length;
            case 'ExposureTime':
                $val = '';
                if (isset($exif[$tag]))
                {
                    if (strstr($exif[$tag], '/'))
                    {
                        // Reduce the fraction
                        $val_parts = explode('/', $exif[$tag]);
                        if ($val_parts[0] >= $val_parts[1])
                        {
                           // Longer than 1 second
                           $val = $val_parts[0] / $val_parts[1];
                        }
                        else
                        {
                            // Less than one second
                            $val = '1/' . ($val_parts[1] / $val_parts[0]);
                        }
                    }
                    elseif ($exif[$tag] < 1)
                    {
                        // Turn the decimal into a fraction
                        $val = '1/' . (1 / $exif[$tag]);
                    }
                    else
                    {
                        $val = $exif[$tag];
                    }
                }
                return $val;
            case 'DateTime':
                $format = ee()->TMPL->fetch_param('format');
                if (isset($exif['DateTimeOriginal']))
                {
                    $date = strtotime($exif['DateTimeOriginal']);
                }
                elseif (isset($exif['DateTime']))
                {
                    $date = strtotime($exif['DateTime']);
                }
                else
                {
                    return '';
                }
                $date += ee()->config->item('vz_exif_offset');
                return $format ? ee()->localize->decode_date($format, $date) : $date;
            case 'Flash':
                return (!@empty($exif['Flash']) && substr(decbin($exif['Flash']), -1) == 1) ? 'Yes' : '';
            case 'GPSLatitude': case 'GPSLongitude':
                return isset($exif[$tag]) && isset($exif[$tag.'Ref']) ? $this->parse_geo($exif[$tag], $exif[$tag.'Ref']) : '';
            default:
                return isset($exif[$tag]) ? $exif[$tag] : '';
        }
    }

    // Parse an array representing a geographic coordinate
    // (hours, minutes, seconds) into a single decimal
    private function parse_geo($geo_array, $ref)
    {
        // Convert the fractions to decimals
        foreach ($geo_array as $element)
        {
            $parts = explode('/', $element);
            $elements[] = $parts[0] / $parts[1];
        }
        $decimal = $elements[0] + ($elements[1] / 60) + ($elements[2] / 3600);
        if ($ref == 'W' || $ref == 'S')
        {
            $decimal *= -1;
        }
        return $decimal;
    }

    public static function usage()
    {
        ob_start();
        ?>

The VZ Exif Plugin extracts Exif data from an image and makes it available in your templates.

TAG PAIR:
=========

The following code will output <em>all</em> the available Exif data in a list. You don't need to include all the variables in your template. You can also use any of the variables in a conditional statement ({if aperture}Aperture: {aperture}{/if}).

{exp:vz_exif image="{photo}"}
<ul>
    <li>File Size: {size}kb</li>
    <li>Dimensions: {width}x{height}</li>
    <li>Taken on: {date format="%F %d, %Y"}</li>
    <li>Camera brand: {make}</li>
    <li>Camera: {model}</li>
    <li>Focal length: {focal_length}mm{if focal_length_equiv} (<abbr title="35mm equivalent">{focal_length_equiv}mm-e</abbr>){/if}</li>
    <li>Aperture: {aperture}</li>
    <li>Shutter speed: {shutter} seconds</li>
    <li>ISO: {iso}</li>
    <li>Processed with: {software}</li>
    <li>Flash used: {flash}</li>
    <li>Latitude: {latitude} / Longitude: {longitude}</li>
</ul>
{/exp:vz_exif}

SINGLE TAGS:
============

You can also get each piece of data using a single tag in the form: {exp:vz_exif:[exif_tag_to_get]}. The Exif data is cached after the first time you get it, so the performance cost for this method should be insignificant.

<p>The following photo was taken with a {exp:vz_exif:model image="{photo}"} camera.</p>

PARAMETERS:
===========

image= (required)
This is the url of the image to get Exif data from. The image needs to be on your server and in JPEG format. Typically, this will be in the form of a custom field inside your {exp:channels} tag pair.

root= (optional)
VZ Exif will do its best to determine the server path to the image you give it, but in some cases you may want to specify the root path manually. The root url will simply be prepended to the image url (minus the domain name, if there is one).

CONFIG.PHP VARIABLES
====================

You can optionally set two variables in Expression Engine's config.php file:

$config['vz_exif_offset'] = 3600;
The number of seconds to add to the Exif timestamp. In some cases, the timestamps will be consistently off by a certain amount. This variable allows you to correct for that. Can be be a negative number.

$config['vz_exif_root'] = "/var/www/public/";
Sets a default server root. This is exactly the same as the tag root parameter, but it saves you from adding that parameter to every tag. The root parameter in your template will override this variable.


        <?php
        $buffer = ob_get_contents();
        ob_end_clean();
        return $buffer;
    }

}

/* End of file pi.vz_exif.php */
/* Location: ./system/expressionengine/third_party/vz_exif/pi.vz_exif.php */