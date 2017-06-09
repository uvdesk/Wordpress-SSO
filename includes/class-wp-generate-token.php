<?php

/**
* Generates a unique random string that can be used be as token as well.
*/

if ( !class_exists( 'WK_TokenGenerator' ) )
{
    class WK_TokenGenerator
    {

        const CODE_LENGTH = 62;
        const CODE_SPAN = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

        /**
        * Generates a unique token.
        *
        * @param integer $length The length of the token to be generated.
        *
        * @return string The generated token.
        */
        public static function wk_generateToken($length = 32)
        {
            $token = '';
            $counter = 0;
            $codeRange = self::CODE_SPAN;
            $codeLength = strlen(self::CODE_SPAN) - 1;

            do {
                $token .= self::crypto_rand_secure(0, $codeLength);
            } while ($length != ++$counter);

            return $token;
        }

        /**
        * Generates a random number.
        *
        * @param integer $minValue Lower bound value.
        * @param integer $maxValue Upper bound value.
        *
        * @return integer Random number within the bounded range.
        */
        private static function crypto_rand_secure($minValue = 0, $maxValue = 0)
        {
            if (($span = ($maxValue - $minValue)) < 1)
                return $minValue;

            $log = ceil(log($span, 2)); // Calculate log
            $bitLength = (int) $log + 1; // Get length in bits
            $byteLength = (int) ($log / 8) + 1; // Get length in bytes
            $filter = (int) (1 << $bitLength) - 1; // Filter bits - Sets all lower bits to 1

            do {
                $randomNumber = hexdec(bin2hex(openssl_random_pseudo_bytes($byteLength)));
                $randomNumber = $randomNumber & $filter; // Discard irrelevant bits
            } while ($randomNumber > $span);

            return $minValue + $randomNumber;
        }

    }

}
