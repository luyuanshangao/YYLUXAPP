<?php
namespace app\common\aes;
    class aes{
        private $localIV = 'AB1234#5C@EDFG7Z';
        private $encryptKey = '1234567$%ABCEDFW';

        //encrypt_openssl新版加密
        function encrypt($str,$table='',$field='')
        {
            if(!empty($table) && !empty($field)){
                $aes_config = config("aes");
                if(isset($aes_config['table_aes'][$table][$field])){
                    $this->encryptKey = $aes_config['Keys'][$aes_config['table_aes'][$table][$field]['Key']];
                    $this->localIV = $aes_config['IVs'][$aes_config['table_aes'][$table][$field]['IV']];
                }
            }
            $encryptKey = $this->encryptKey;
            $localIV = $this->localIV;
            return openssl_encrypt($str, 'AES-128-CBC',$encryptKey,0,$localIV);
        }
        //decrypt_openssl新版解密
        function decrypt($str,$table='',$field='')
        {
            if(!empty($table) && !empty($field)){
                $aes_config = config("aes");
                if(isset($aes_config['table_aes'][$table][$field])){
                    $this->encryptKey = $aes_config['Keys'][$aes_config['table_aes'][$table][$field]['Key']];
                    $this->localIV = $aes_config['IVs'][$aes_config['table_aes'][$table][$field]['IV']];
                }
            }
            $encryptKey = $this->encryptKey;
            $localIV = $this->localIV;
            return openssl_decrypt($str, 'AES-128-CBC', $encryptKey, 0, $localIV);
        }

    }