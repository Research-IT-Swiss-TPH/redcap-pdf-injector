<?php namespace STPH\pdfInjector;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once __DIR__ . '/../../../redcap_connect.php';

use \GuzzleHttp\Psr7;
use \HttpClient;
use \GuzzleHttp\Exception\ClientException;


class RequestHandlerTest extends BaseTest {

    private $http;
    private $base_url;

    public function setUp(): void
    {
        parent::setUp();

        $this->http = new HttpClient;
        $this->base_url = $this->getUrl("requestHandler.php");
    }

    public function tearDown():void {
        $this->http = null;
     }

    /**
     * scanFile()
     * 
     * @since 1.3.7
     */

    function testScanFile_throws_for_no_file_set() {

       
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage("Unknown Error");

        $this->http::request('post', $this->base_url, [
            "form_params" => [
                "action" => "fileScan"
            ]
        ]);
        
    }

    function testScanFile_throws_for_invalid_file() {

        $requestURL = $this->base_url . "&action=fileScan";
        
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Invalid file type.');

        $this->http::request('post', $requestURL, [
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => Psr7\Utils::tryFopen( __DIR__ . '/files/invalid_file.txt', 'r')
                ]
            ]
        ]);
    }

    function testScanFile_throws_for_invalid_pdf(){

        $requestURL = $this->base_url . "&action=fileScan";

        $this->expectExceptionMessage('Invalid PDF.');
        $this->http::request('post', $requestURL, [
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => Psr7\Utils::tryFopen( __DIR__ .  '/files/pdfi_blank_unreadable.pdf', 'r')
                ]
               
            ]
        ]);
    }

    function testScanFile_succeeds(){

        $requestURL = $this->base_url . "&action=fileScan";

        $response = $this->http::request('post', $requestURL, [
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => Psr7\Utils::tryFopen( __DIR__ .  '/files/pdfi_blank_readable.pdf', 'r')
                ]
               
            ]
        ]);

        $responseData = json_decode($response->getBody()->getContents())->fieldData;

        $this->assertEquals(count($responseData), 8);
    }

    /**
     * scanField()
     * 
     * @since 1.3.7
     */
    function testScanField_fails_for_no_fieldName_set() {

        $requestURL = $this->base_url . "&action=fieldScan";
        
        $this->expectExceptionMessage("Field is invalid");
        $this->http::request('post', $requestURL, []);

    }

    function testScanField_fails_for_invalid_fieldName_set() {

        $requestURL = $this->base_url . "&action=fieldScan";
        
        $this->expectExceptionMessage("Field is invalid");
        $this->http::request('post', $requestURL, [
            "fieldName" => "non-existing-field"
        ]);

    }

    function testScanField_succeeds() {

        //  We have to send pid
        $requestURL = $this->base_url . "&action=fieldScann&pid=" . PROJECT_ID;

        $response = $this->http::request('post', $requestURL, [
            "fieldName" => "'record_id'"
        ]);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * previewInjection()
     * 
     * @since 1.3.8
     */
    function testPreviewInjection_fails_without_document_id() {

        $requestURL = $this->base_url . "&action=previewInjection";

        $this->expectExceptionMessage("Document ID missing.");
        $this->http::request('post', $requestURL, []);

    }

    function testPreviewInjection_fails_without_injection_data_set() {
        $requestURL = $this->base_url . "&action=previewInjection";
        $this->expectExceptionMessage("No injection data available.");
        $this->http::request('post', $requestURL, [
            "form_params" => [
                "document_id" => 1
            ]
        ]);
    }

    function testPreviewInjection_fails_with_invalid_document_id() {
        
        $requestURL = $this->base_url . "&action=previewInjection";
      
        $testData = [
            3 => [
                "foo" => "bar"
            ]
        ];

        $this->module->setProjectSetting('pdf-injections', $testData);

        $response  = $this->http->request('post', $requestURL, [
            "form_params" => [
                "document_id" => 3
            ]
        ]);

        dump($response->getBody()->getContents());
    }

}