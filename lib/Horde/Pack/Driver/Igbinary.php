<?php

/**
 * Copyright 2013-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2013-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Pack
 */

/**
 * PHP igbinary extension handler.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Pack
 */
class Horde_Pack_Driver_Igbinary extends Horde_Pack_Driver
{
    /**
     */
    protected $_id = 4;

    /**
     */
    protected $_phpob = true;

    /**
     */
    public static function supported()
    {
        return extension_loaded('igbinary');
    }

    /**
     */
    public function pack($data)
    {
        return igbinary_serialize($data);
    }

    /**
     */
    public function unpack($data)
    {
        // Only warnings emitted by the igbinary extension itself count
        // as an unpack failure. Warnings/notices raised from within an
        // object's __wakeup / __unserialize (e.g. IMP_Imap re-attaching
        // its debug fopen resource) are semantically unrelated to
        // whether the payload decoded, and must not discard a correctly
        // reconstructed graph. This is the regression pack v2.0.0
        // shipped when the previous @-suppression idiom was replaced
        // with a blanket set_error_handler that flipped on ANY warning:
        // the @ operator no longer zeros error_reporting() on modern
        // PHP, so error-reporting-based detection isn't reliable
        // either. Message-prefix matching is what actually
        // discriminates.
        $error = false;
        set_error_handler(function ($errno, $errstr) use (&$error) {
            if (str_starts_with((string) $errstr, 'igbinary_')) {
                $error = true;
                return true;
            }
            return false;
        });
        try {
            $out = igbinary_unserialize($data);
        } finally {
            restore_error_handler();
        }

        if (!$error && (!is_null($out) || ($data == igbinary_serialize(null)))) {
            return $out;
        }

        throw new Horde_Pack_Exception('Error when unpacking serialized data.');
    }

}
