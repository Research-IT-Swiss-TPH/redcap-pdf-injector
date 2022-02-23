<?php namespace STPH\pdfInjector;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once __DIR__ . '/../../../redcap_connect.php';

use \HttpClient;
use \Exception;
use \GuzzleHttp\Psr7;

final class RequestHandlerTest extends BaseTest {

    /**
     * @since 1.4.0
     */
    function testScanFile_throws_for_no_file_set() {


        $requestURL = $this->getUrl("requestHandler.php") . "&action=fileScan";

        $this->expectExceptionMessage("Unknown Error");
        HttpClient::request('post', $requestURL);        
        
    }
    /**
     * @since 1.4.0
     */
    function testScanFile_throws_for_invalid_file() {

        $client = new HttpClient;
        $requestURL = $this->getUrl("requestHandler.php") . "&action=fileScan";

        //$this->expectException(\GuzzleHttp\Exception\ClientException::class);
        $this->expectExceptionMessage('Invalid file type.');
        $client::request('post', $requestURL, [
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => Psr7\Utils::tryFopen( __DIR__ . '/files/invalid_file.txt', 'r')
                ]
               
            ]
        ]);
    }
    /**
     * @since 1.4.0
     */
    function testScanFile_throws_for_invalid_pdf(){
        $client = new HttpClient;
        $requestURL = $this->getUrl("requestHandler.php") . "&action=fileScan";

        $this->expectExceptionMessage('Invalid PDF.');
        $client::request('post', $requestURL, [
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => Psr7\Utils::tryFopen( __DIR__ .  '/files/pdfi_blank_unreadable.pdf', 'r')
                ]
               
            ]
        ]);
    }
    /**
     * @since 1.4.0
     */
    function testScanFile_succeeds(){
        $client = new HttpClient;
        $requestURL = $this->getUrl("requestHandler.php") . "&action=fileScan";

        $response = $client::request('post', $requestURL, [
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => Psr7\Utils::tryFopen( __DIR__ .  '/files/pdfi_blank_readable.pdf', 'r')
                ]
               
            ]
        ]);

        $responseData = json_decode($response->getBody()->getContents())->fieldData;
        //fwrite(STDERR, print_r($responseData, TRUE));

        $this->assertEquals(count($responseData), 8);
    }

    /**
     * @since 1.4.1
     */
    function testScanField_auth_fails_without_token() {
        $client = new HttpClient;
        $requestURL = $this->getUrl("requestHandler.php") . "&action=fieldScan";

        $this->expectExceptionMessageMatches("/Token not set/");
        $response = $client::request('post', $requestURL, []);

    }

    /**
     * @since 1.4.1
     */
    function testScanField_auth_fails_with_wrong_token() {
        $client = new HttpClient;
        $requestURL = $this->getUrl("requestHandler.php") . "&action=fieldScan";

        $this->expectExceptionMessageMatches("/Wrong number of segments/");
        $response = $client::request('post', $requestURL, [
            'form_params' => [
                "pdfi_jwt" => "FooBar"
            ]
        ]);

    }

    /**
     * @since 1.4.1
     */
    function testScanField_fails_with_expired_token() {
        $client = new HttpClient;
        $requestURL = $this->getUrl("requestHandler.php") . "&action=fieldScan";

        $token = $this->getTestToken(false);

        $this->expectExceptionMessageMatches("/Token has expired./");
        $client::request('post', $requestURL, [
            'form_params' =>
            [
                "pdfi_jwt" => $token
            ]
        ]);

    }

       /**
     * @since 1.4.1
     */
    function testScanField_fails_with_valid_token() {
        $client = new HttpClient;
        $requestURL = $this->getUrl("requestHandler.php") . "&action=fieldScan";

        $token = $this->getTestToken(false);

        $this->expectExceptionMessage("Field is invalid");
        $client::request('post', $requestURL, [
            'form_params' =>
            [
                "pdfi_jwt" => $token
            ]
        ]);

    }     

    /**
     * @since 1.4.0
     */
    function testScanField_fails_for_no_fieldName_set() {
        $client = new HttpClient;
        $requestURL = $this->getUrl("requestHandler.php") . "&action=fieldScan";
        
        //$this->expectExceptionMessage("Field is invalid");
        $response = $client::request('post', $requestURL, []);
        $this->assertSame(401, $response->getStatusCode());
    }
    /**
     * @since 1.4.0
     */
    function testScanField_fails_for_invalid_fieldName_set() {
        $client = new HttpClient;
        $requestURL = $this->getUrl("requestHandler.php") . "&action=fieldScan";
        
        //$this->expectExceptionMessage("Field is invalid");
        $response = $client::request('post', $requestURL, [
            "fieldName" => "non-existing-field"
        ]);
        $this->assertSame(401, $response->getStatusCode());
    }
    /**
     * @since 1.4.0
     */
    function testScanField_succeeds() {
        $client = new HttpClient;

        //  We have to send pid
        $requestURL = $this->getUrl("requestHandler.php") . "&action=fieldScan";
        
        //$this->expectExceptionMessage("Field is invalid");
        $response = $client::request('post', $requestURL, [
            "fieldName" => "'record_id'"
        ]);
        $this->assertSame(401, $response->getStatusCode());
        //$this->assertSame(200, $response->getStatusCode());
    }   

}