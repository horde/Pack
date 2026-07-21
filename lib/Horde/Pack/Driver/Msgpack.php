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
 * PHP msgpack extension handler (non-serialized methods).
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Pack
 */
class Horde_Pack_Driver_Msgpack extends Horde_Pack_Driver
{
    /**
     */
    protected $_id = 16;

    /**
     */
    public static function supported()
    {
        return extension_loaded('msgpack');
    }

    /**
     */
    public function pack($data)
    {
        return msgpack_pack($data);
    }

    /**
     */
    public function unpack($data)
    {
        // Only warnings emitted by the msgpack extension itself count as
        // an unpack failure. Warnings/notices from an object's __wakeup /
        // __unserialize must not discard a correctly reconstructed
        // graph. See {@see Horde_Pack_Driver_Igbinary::unpack()} for the
        // full rationale.
        $error = false;
        set_error_handler(function ($errno, $errstr) use (&$error) {
            if (str_starts_with((string) $errstr, '[msgpack]')) {
                $error = true;
                return true;
            }
            return false;
        });
        try {
            $out = msgpack_unpack($data);
        } finally {
            restore_error_handler();
        }

        if (!$error) {
            return $out;
        }

        throw new Horde_Pack_Exception('Error when unpacking Msgpack data.');
    }

}
