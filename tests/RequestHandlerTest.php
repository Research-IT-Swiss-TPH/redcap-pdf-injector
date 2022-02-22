<?php namespace STPH\pdfInjector;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once __DIR__ . '/../../../redcap_connect.php';

use \HttpClient;
use \Exception;
use \GuzzleHttp\Psr7;


final class RequestHandlerTest extends BaseTest {


    /**
     * scanFile()
     * 
     * @since 1.4.0
     */

    function testScanFile_throws_for_no_file_set() {

        $client = new HttpClient;
        $requestURL = $this->getUrl("requestHandler.php") . "&action=fileScan";

        $this->expectExceptionMessage("Unknown Error");
        $client::request('post', $requestURL);        
        
    }

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
     * scanField()
     * 
     * @since 1.4.0
     */
    function testScanField_fails_for_no_fieldName_set() {
        $client = new HttpClient;
        $requestURL = $this->getUrl("requestHandler.php") . "&action=fieldScan";
        
        $this->expectExceptionMessage("Field is invalid");
        $client::request('post', $requestURL, []);

    }

    function testScanField_fails_for_invalid_fieldName_set() {
        $client = new HttpClient;
        $requestURL = $this->getUrl("requestHandler.php") . "&action=fieldScan";
        
        $this->expectExceptionMessage("Field is invalid");
        $client::request('post', $requestURL, [
            "fieldName" => "non-existing-field"
        ]);

    }

    function testScanField_succeeds() {
        $client = new HttpClient;

        //  We have to send pid
        $requestURL = $this->getUrl("requestHandler.php") . "&action=fieldScan&pid=" . PROJECT_ID;
        
        $response = $client::request('post', $requestURL, [
            "fieldName" => "'record_id'"
        ]);

        $this->assertSame(200, $response->getStatusCode());
    }   

}