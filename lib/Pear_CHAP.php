<?php
/*
Copyright (c) 2002-2010, Michael Bretterklieber <michael@bretterklieber.com>
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:

1. Redistributions of source code must retain the above copyright
   notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
   notice, this list of conditions and the following disclaimer in the
   documentation and/or other materials provided with the distribution.
3. The names of the authors may not be used to endorse or promote products
   derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

This code cannot simply be copied and put under the GNU Public License or
any other GPL-like (LGPL, GPL2) License.

    $Id: CHAP.php 302857 2010-08-28 21:12:59Z mbretter $

This version of CHAP.php has been modified by Drew Phillips for dapphp/radius.
Modifications remove the PEAR dependency, change from PHP4 OOP to PHP5, and
mcrypt functions have been replaced with openssl_* functions.

Changes are all commented inline throughout the source.

    $Id: Pear_CHAP.php 2.5.2 2018-01-25  03:30:29Z dapphp $

*/

// require_once 'PEAR.php'; // removed for dapphp/radius

/**
* Classes for generating packets for various CHAP Protocols:
* CHAP-MD5: RFC1994
* MS-CHAPv1: RFC2433
* MS-CHAPv2: RFC2759
*
* @package Crypt_CHAP
* @author  Michael Bretterklieber <michael@bretterklieber.com>
* @access  public
*/

/**
 * class Crypt_CHAP
 *
 * Abstract base class for CHAP
 *
 * @package Crypt_CHAP
 */
class Crypt_CHAP /*extends PEAR // removed for dapphp/radius */
{
    /**
     * Random binary challenge
     * @var  string
     */
    public $challenge = null;
    //var $challenge = null;  // removed for dapphp/radius

    /**
     * Binary response
     * @var  string
     */
    public $response = null;
    //var $response = null;  // removed for dapphp/radius

    /**
     * User password
     * @var  string
     */
    public $password = null;
    //var $password = null;  // removed for dapphp/radius

    /**
     * Id of the authentication request. Should incremented after every request.
     * @var  integer
     */
    public $chapid = 1;
    //var $chapid = 1;  // removed for dapphp/radius

    /**
     * Constructor
     *
     * Generates a random challenge
     * @return void
     */
    //function Crypt_CHAP()  // removed for dapphp/radius
    public function __construct()
    {
        //$this->PEAR();
        $this->generateChallenge();
    }

    /**
     * Generates a random binary challenge
     *
     * @param  string  $varname  Name of the property
     * @param  integer $size     Size of the challenge in Bytes
     * @return void
     */
    //function generateChallenge($varname = 'challenge', $size = 8)  // removed for dapphp/radius
    public function generateChallenge($varname = 'challenge', $size = 8)
    {
        $this->$varname = '';
        for ($i = 0; $i < $size; $i++) {
            $this->$varname .= pack('C', 1 + mt_rand() % 255);
        }
        return $this->$varname;
    }

    /**
     * Generates the response. Overwrite this.
     *
     * @return void
     */
    //function challengeResponse()  // removed for dapphp/radius
    public function challengeResponse()
    {
    }

}

/**
 * class Crypt_CHAP_MD5
 *
 * Generate CHAP-MD5 Packets
 *
 * @package Crypt_CHAP
 */
class Crypt_CHAP_MD5 extends Crypt_CHAP
{

    /**
     * Generates the response.
     *
     * CHAP-MD5 uses MD5-Hash for generating the response. The Hash consists
     * of the chapid, the plaintext password and the challenge.
     *
     * @return string
     */
    //function challengeResponse()  // removed for dapphp/radius
    public function challengeResponse()
    {
        return pack('H*', md5(pack('C', $this->chapid) . $this->password . $this->challenge));
    }
}

/**
 * class Crypt_CHAP_MSv1
 *
 * Generate MS-CHAPv1 Packets. MS-CHAP doesen't use the plaintext password, it uses the
 * NT-HASH wich is stored in the SAM-Database or in the smbpasswd, if you are using samba.
 * The NT-HASH is MD4(str2unicode(plaintextpass)).
 * You need the hash extension for this class.
 *
 * @package Crypt_CHAP
 */
class Crypt_CHAP_MSv1 extends Crypt_CHAP
{
    /**
     * Wether using deprecated LM-Responses or not.
     * 0 = use LM-Response, 1 = use NT-Response
     * @var  bool
     */
    protected $flags = 1;
    //var $flags = 1;  // removed for dapphp/radius

    protected $useMcrypt = false; // added for dapphp/radius (php 5.3 must use mcrypt)

    /**
     * Constructor
     *
     * Loads the hash extension
     * @return void
     */
    //function Crypt_CHAP_MSv1()  // removed for dapphp/radius
    public function __construct()
    {
        parent::__construct();

        // removed for dapphp/radius
        //$this->Crypt_CHAP();
        //$this->loadExtension('hash');

        // added openssl & mcrypt check for dapphp/radius
        if (!extension_loaded('openssl') && !extension_loaded('mcrypt')) {
            throw new \Exception("openssl and mcrypt are not installed; cannot use Radius MSCHAP functions");
        }

        // Added mcrypt check for PHP 5.3 for dapphp/radius
        // OPENSSL_RAW_DATA and OPENSSL_ZERO_PADDING are required but not
        // supported by ext/openssl until PHP 5.4.
        if (version_compare(PHP_VERSION, '5.4') < 0) {
            if (!extension_loaded('mcrypt')) {
                throw new \Exception("Radius MSCHAP functions require mcrypt extension for PHP 5.3");
            }

            $this->useMcrypt = true;
        }
    }

    /**
     * Generates the NT-HASH from the given plaintext password.
     *
     * @access public
     * @return string
     */
    //function ntPasswordHash($password = null)  // removed for dapphp/radius
    public function ntPasswordHash($password = null)
    {
        //if (isset($password)) {
        if (!is_null($password)) {
            return pack('H*',hash('md4', $this->str2unicode($password)));
        } else {
            return pack('H*',hash('md4', $this->str2unicode($this->password)));
        }
    }

    /**
     * Converts ascii to unicode.
     *
     * @access public
     * @return string
     */
    //function str2unicode($str)  // removed for dapphp/radius
    public function str2unicode($str)
    {
        return mb_convert_encoding($str, 'UTF-16LE');
    }

    /**
     * Generates the NT-Response.
     *
     * @access public
     * @return string
     */
    //function challengeResponse()  // removed for dapphp/radius
    public function challengeResponse()
    {
        return $this->_challengeResponse();
    }

    /**
     * Generates the NT-Response.
     *
     * @access public
     * @return string
     */
    //function ntChallengeResponse()  // removed for dapphp/radius
    public function ntChallengeResponse()
    {
        return $this->_challengeResponse(false);
    }

    /**
     * Generates the LAN-Manager-Response.
     *
     * @access public
     * @return string
     */
    //function lmChallengeResponse()  // removed for dapphp/radius
    public function lmChallengeResponse()
    {
        return $this->_challengeResponse(true);
    }

    /**
     * Generates the response.
     *
     * Generates the response using DES.
     *
     * @param  bool  $lm  wether generating LAN-Manager-Response
     * @access private
     * @return string
     */
    //function _challengeResponse($lm = false)  // removed for dapphp/radius
    protected function _challengeResponse($lm = false)
    {
        if ($lm) {
            $hash = $this->lmPasswordHash();
        } else {
            $hash = $this->ntPasswordHash();
        }

        $hash = str_pad($hash, 21, "\0");

        if (extension_loaded('openssl') && $this->useMcrypt === false) {
            // added openssl routines for dapphp/radius
            $key   = $this->_desAddParity(substr($hash, 0, 7));
            $resp1 = openssl_encrypt($this->challenge, 'des-ecb', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);

            $key   = $this->_desAddParity(substr($hash, 7, 7));
            $resp2 = openssl_encrypt($this->challenge, 'des-ecb', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);

            $key   = $this->_desAddParity(substr($hash, 14, 7));
            $resp3 = openssl_encrypt($this->challenge, 'des-ecb', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
        } else {
            $td = mcrypt_module_open(MCRYPT_DES, '', MCRYPT_MODE_ECB, '');
            $iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
            $key = $this->_desAddParity(substr($hash, 0, 7));
            mcrypt_generic_init($td, $key, $iv);
            $resp1 = mcrypt_generic($td, $this->challenge);
            mcrypt_generic_deinit($td);

            $key = $this->_desAddParity(substr($hash, 7, 7));
            mcrypt_generic_init($td, $key, $iv);
            $resp2 = mcrypt_generic($td, $this->challenge);
            mcrypt_generic_deinit($td);

            $key = $this->_desAddParity(substr($hash, 14, 7));
            mcrypt_generic_init($td, $key, $iv);
            $resp3 = mcrypt_generic($td, $this->challenge);
            mcrypt_generic_deinit($td);
            mcrypt_module_close($td);
        }

        return $resp1 . $resp2 . $resp3;
    }

    /**
     * Generates the LAN-Manager-HASH from the given plaintext password.
     *
     * @access public
     * @return string
     */
    //function lmPasswordHash($password = null)  // removed for dapphp/radius
    public function lmPasswordHash($password = null)
    {
        $plain = isset($password) ? $password : $this->password;

        $plain = substr(strtoupper($plain), 0, 14);
        while (strlen($plain) < 14) {
             $plain .= "\0";
        }

        return $this->_desHash(substr($plain, 0, 7)) . $this->_desHash(substr($plain, 7, 7));
    }

    /**
     * Generates an irreversible HASH.
     *
     * @access private
     * @return string
     */
    //function _desHash($plain)  // removed for dapphp/radius
    private function _desHash($plain)
    {
        if (extension_loaded('openssl') && $this->useMcrypt === false) {
            // added openssl routines for dapphp/radius
            $key = $this->_desAddParity($plain);
            $hash = openssl_encrypt('KGS!@#$%', 'des-ecb', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);

            return $hash;
        } else {
            $key = $this->_desAddParity($plain);
            $td = mcrypt_module_open(MCRYPT_DES, '', MCRYPT_MODE_ECB, '');
            $iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
            mcrypt_generic_init($td, $key, $iv);
            $hash = mcrypt_generic($td, 'KGS!@#$%');
            mcrypt_generic_deinit($td);
            mcrypt_module_close($td);

            return $hash;
        }
    }

    /**
     * Adds the parity bit to the given DES key.
     *
     * @access private
     * @param  string  $key 7-Bytes Key without parity
     * @return string
     */
    //function _desAddParity($key)  // removed for dapphp/radius
    protected function _desAddParity($key)
    {
        static $odd_parity = array(
                1,  1,  2,  2,  4,  4,  7,  7,  8,  8, 11, 11, 13, 13, 14, 14,
                16, 16, 19, 19, 21, 21, 22, 22, 25, 25, 26, 26, 28, 28, 31, 31,
                32, 32, 35, 35, 37, 37, 38, 38, 41, 41, 42, 42, 44, 44, 47, 47,
                49, 49, 50, 50, 52, 52, 55, 55, 56, 56, 59, 59, 61, 61, 62, 62,
                64, 64, 67, 67, 69, 69, 70, 70, 73, 73, 74, 74, 76, 76, 79, 79,
                81, 81, 82, 82, 84, 84, 87, 87, 88, 88, 91, 91, 93, 93, 94, 94,
                97, 97, 98, 98,100,100,103,103,104,104,107,107,109,109,110,110,
                112,112,115,115,117,117,118,118,121,121,122,122,124,124,127,127,
                128,128,131,131,133,133,134,134,137,137,138,138,140,140,143,143,
                145,145,146,146,148,148,151,151,152,152,155,155,157,157,158,158,
                161,161,162,162,164,164,167,167,168,168,171,171,173,173,174,174,
                176,176,179,179,181,181,182,182,185,185,186,186,188,188,191,191,
                193,193,194,194,196,196,199,199,200,200,203,203,205,205,206,206,
                208,208,211,211,213,213,214,214,217,217,218,218,220,220,223,223,
                224,224,227,227,229,229,230,230,233,233,234,234,236,236,239,239,
                241,241,242,242,244,244,247,247,248,248,251,251,253,253,254,254);

        $bin = '';
        for ($i = 0; $i < strlen($key); $i++) {
            $bin .= sprintf('%08s', decbin(ord($key{$i})));
        }

        $str1 = explode('-', substr(chunk_split($bin, 7, '-'), 0, -1));
        $x = '';
        foreach($str1 as $s) {
            $x .= sprintf('%02s', dechex($odd_parity[bindec($s . '0')]));
        }

        return pack('H*', $x);

    }

    /**
     * Generates the response-packet.
     *
     * @param  bool  $lm  wether including LAN-Manager-Response
     * @access private
     * @return string
     */
    //function response($lm = false)  // removed for dapphp/radius
    public function response($lm = false)
    {
        $ntresp = $this->ntChallengeResponse();
        if ($lm) {
            $lmresp = $this->lmChallengeResponse();
        } else {
            $lmresp = str_repeat ("\0", 24);
        }

        // Response: LM Response, NT Response, flags (0 = use LM Response, 1 = use NT Response)
        return $lmresp . $ntresp . pack('C', !$lm);
    }
}

/**
 * class Crypt_CHAP_MSv2
 *
 * Generate MS-CHAPv2 Packets. This version of MS-CHAP uses a 16 Bytes authenticator
 * challenge and a 16 Bytes peer Challenge. LAN-Manager responses no longer exists
 * in this version. The challenge is already a SHA1 challenge hash of both challenges
 * and of the username.
 *
 * @package Crypt_CHAP
 */
class Crypt_CHAP_MSv2 extends Crypt_CHAP_MSv1
{
    /**
     * The username
     * @var  string
     */
    public $username = null;
    //var $username = null; // removed for dapphp/radius

    /**
     * The 16 Bytes random binary peer challenge
     * @var  string
     */
    public $peerChallenge = null;
    //var $peerChallenge = null;  // removed for dapphp/radius

    /**
     * The 16 Bytes random binary authenticator challenge
     * @var  string
     */
    public $authChallenge = null;
    //var $authChallenge = null;  // removed for dapphp/radius

    /**
     * The new password to be defined
     * @var  string
     */
    public $newPassword = null;

    /**
     * Constructor
     *
     * Generates the 16 Bytes peer and authentication challenge
     * @return void
     */
    //function Crypt_CHAP_MSv2()  // removed for dapphp/radius
    public function __construct()
    {
        //$this->Crypt_CHAP_MSv1();  // removed for dapphp/radius
        parent::__construct();
        $this->generateChallenge('peerChallenge', 16);
        $this->generateChallenge('authChallenge', 16);
    }

    /**
     * Generates a hash from the NT-HASH.
     *
     * @access public
     * @param  string  $nthash The NT-HASH
     * @return string
     */
    //function ntPasswordHashHash($nthash)  // removed for dapphp/radius
    public function ntPasswordHashHash($nthash)
    {
        return pack('H*',hash('md4', $nthash));
    }

    /**
     * Generates the challenge hash from the peer and the authenticator challenge and
     * the username. SHA1 is used for this, but only the first 8 Bytes are used.
     *
     * @access public
     * @return string
     */
    //function challengeHash()  // removed for dapphp/radius
    public function challengeHash()
    {
        return substr(pack('H*',hash('sha1', $this->peerChallenge . $this->authChallenge . $this->username)), 0, 8);
    }

    /**
     * Generates the response.
     *
     * @access public
     * @return string
     */
    //function challengeResponse()  // removed for dapphp/radius
    public function challengeResponse()
    {
        $this->challenge = $this->challengeHash();
        return $this->_challengeResponse();
    }

    /**
     * Generates the encrypted new password.
     *
     * @access public
     * @param  string  $NewPassword The new plain text password
     * @param  string  $OldPassword The old plain text password
     * @return string  EncryptedPwBlock
     */
    public function NewPasswordEncryptedWithOldNtPasswordHash()
    {
        $PasswordHash = $this->NtPasswordHash($this->password);
        return $this->EncryptPwBlockWithPasswordHash($this->str2unicode($this->newPassword), $PasswordHash);
    }

    /**
     * Generates PwBlock
     *
     * @access public
     * @param  string  $Password     New password
     * @param  string  $PasswordHash Old password hash
     * @return string  PwBlock
     */
    public function EncryptPwBlockWithPasswordHash($Password, $PasswordHash)
    {
        // [516=2*256+4] unicode(2) maxpasslength(256) passlength(4)
        $ClearPwBlock = random_bytes(516);
        $PwSize       = strlen($Password);
        $PwOffset     = strlen($ClearPwBlock) - $PwSize - 4;

        $ClearPwBlock = substr_replace($ClearPwBlock, $Password, $PwOffset, $PwSize);

        $ClearPwBlock = substr_replace($ClearPwBlock, pack("V", $PwSize), -4, 4);

        return $this->rc4($PasswordHash, $ClearPwBlock);
    }

	/**
	 * RC4 symmetric cipher encryption/decryption
	 *
	 * @access public
	 * @param  string key - secret key for encryption/decryption
	 * @param  string str - string to be encrypted/decrypted
	 * @return string
	 */
    public function rc4($key, $str)
    {
        $s = array();
        for ($i = 0; $i < 256; $i++) {
            $s[$i] = $i;
        }
        $j = 0;
        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $s[$i] + ord($key[$i % strlen($key)])) % 256;
            $x = $s[$i];
            $s[$i] = $s[$j];
            $s[$j] = $x;
        }
        $i = 0;
        $j = 0;
        $res = '';
        for ($y = 0; $y < strlen($str); $y++) {
            $i = ($i + 1) % 256;
            $j = ($j + $s[$i]) % 256;
            $x = $s[$i];
            $s[$i] = $s[$j];
            $s[$j] = $x;
            $res .= $str[$y] ^ chr($s[($s[$i] + $s[$j]) % 256]);
        }
        return $res;
    }

    /**
     * ?
     *
     * @access public
     * @param  string  $NewPassword The new plain text password
     * @param  string  $OldPassword The old plain text password
     * @return string  EncryptedPasswordHash
     */
    public function OldNtPasswordHashEncryptedWithNewNtPasswordHash()
    {
        $OldPasswordHash = $this->NtPasswordHash($this->password);
        $NewPasswordHash = $this->NtPasswordHash($this->newPassword);
        return $this->NtPasswordHashEncryptedWithBlock($OldPasswordHash, $NewPasswordHash);
    }

    /**
     * ?
     *
     * @access public
     * @param  string  $PasswordHash Password hash to encrypt
     * @param  string  $Block        Key to use for encryption
     * @return string
     */
    public function NtPasswordHashEncryptedWithBlock($PasswordHash, $Block)
    {
        if (extension_loaded('openssl') && $this->useMcrypt === false) {
            $key   = $this->_desAddParity(substr($Block, 0, 7));
            $resp1 = openssl_encrypt(substr($PasswordHash, 0, 8), 'des-ecb', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);

            $key   = $this->_desAddParity(substr($Block, 7, 7));
            $resp2 = openssl_encrypt(substr($PasswordHash, 8, 8), 'des-ecb', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
        } else {
            $td = mcrypt_module_open(MCRYPT_DES, '', MCRYPT_MODE_ECB, '');
            $iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);

            $key = $this->_desAddParity(substr($Block, 0, 7));
            mcrypt_generic_init($td, $key, $iv);
            $resp1 = mcrypt_generic($td, substr($PasswordHash, 0, 8));
            mcrypt_generic_deinit($td);

            $key   = $this->_desAddParity(substr($Block, 7, 7));
            mcrypt_generic_init($td, $key, $iv);
            $resp2 = mcrypt_generic($td, substr($PasswordHash, 8, 8));
            mcrypt_generic_deinit($td);
        }
        return $resp1 . $resp2;
    }

}
