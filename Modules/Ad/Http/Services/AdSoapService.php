<?php

namespace Modules\Ad\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SoapClient;
use SoapHeader;

class AdSoapService
{
    public function makeSoapRequest($data)
    {


        $client = new SoapClient('http://103.229.177.10:1119/tran.wsdl', [
            'soapVersion' => SOAP_1_2,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'allow_self_signed' => true
                ],
            ])
        ]);

        $request = [
            'InitiatorRid' => 'RTP43RDPARTY',
            'LifePhase' => 'Single',
            'Kind' => 'ReadSubject',
            'ProcessorInstName' => 'Test',
            'Specific' => [
                'Admin' => [
                    '_ObjectMustExist' => true,
                    'Subject' => [
                        'Person' => [
                            'Rid' => '9214101012',
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = $client->__soapCall('tran:Request', [$request]);

            // Process the SOAP response as needed
            return $response;
        } catch (\SoapFault $fault) {
            // Handle SOAP faults or exceptions
            return $fault->getMessage();
        }
    }

    private function buildSoapRequest($data)
    {
        // Build your SOAP request XML here
        // You may use a library like DOMDocument to create XML
        $xml = '<SOAP-ENV:Envelope
        xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
        SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
         <SOAP-ENV:Body>
         <tran:Request InitiatorRid="RTP43RDPARTY" LifePhase="Single" Kind="ReadSubject" ProcessorInstName="Test" xmlns:tran="http://schemas.tranzaxis.com/tran.xsd"
            xmlns:sub="http://schemas.tranzaxis.com/subjects-admin.xsd">
                        <tran:Specific>
                            <tran:Admin ObjectMustExist="true">
                                <tran:Subject>
                                    <sub:Person>
                                        <sub:Rid>9214101012</sub:Rid>
                                    </sub:Person>
                                </tran:Subject>
                            </tran:Admin>
                        </tran:Specific>
                    </tran:Request>
         </SOAP-ENV:Body>
      </SOAP-ENV:Envelope>';

        return $xml;
    }
}
