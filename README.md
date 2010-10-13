Easy creation of WSDL documents
===============================

Create a simple WSDL document from the documented PHP classes.

Assumptions
-----------

- Expose only `public` methods
- Adding a whole class
- Class must be documented

Example PHP class
-----------------

    /**
     * Sample class of service
     */
    class Sample_Service {

        /**
         * Test service function
         *
         * @param string $first  First string
         * @param int    $second Second is number
         * @param array  $third  Third is an array
         * @return string This result description
         */
        public function test_method($first, $second, $third) {

        }
    }

Example usage
-------------

    // create new parser
    $wsdl = new Wsdl_Document();

    // set service name
    $wsdl->set_name('MyService');

    // add an array of classes
    $wsdl->add_class(array(
        'Sample_Service' => 'http://example.com/server',
    ));

    $wsdl->save('document.wsdl')); // Result: bool

    $wsdl->get_document());        // Result: string - WSDL document

    $wsdl->validate();             // Result: bool


The resulting WSDL document
---------------------------

    <?xml version="1.0"?>
    <definitions
            xmlns:typens="urn:MyService"
            xmlns:xsd="http://www.w3.org/2001/XMLSchema"
            xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/"
            xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/"
            xmlns:wsdl="http://schemas.xmlsoap.org/wsdl/"
            xmlns="http://schemas.xmlsoap.org/wsdl/"
            name="MyService"
            targetNamespace="urn:MyService">
        <message name="test_method">
            <part name="first" type="xsd:string"/>
            <part name="second" type="xsd:int"/>
            <part name="third" type="soapenc:Array"/>
        </message>
        <message name="test_methodResponse">
            <part name="test_methodReturn" type="xsd:string"/>
        </message>
        <portType name="Sample_ServicePortType">
            <operation name="test_method">
                <documentation>Test service function</documentation>
                <input message="typens:test_method"/>
                <output message="typens:test_methodResponse"/>
            </operation>
        </portType>
        <binding name="Sample_ServiceBinding" type="typens:Sample_ServicePortType">
            <soap:binding style="rpc" transport="http://schemas.xmlsoap.org/soap/http"/>
            <operation name="test_method">
                <soap:operation soapAction="urn:test_methodAction"/>
                <input>
                    <soap:body namespace="urn:MyService" use="encoded"
                        encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"/>
                </input>
                <output>
                    <soap:body namespace="urn:MyService" use="encoded"
                        encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"/>
                </output>
            </operation>
        </binding>
        <service name="MyServiceService">
            <port name="Sample_ServicePort" binding="typens:Sample_ServiceBinding">
                <soap:address location="http://example.com/server"/>
            </port>
        </service>
    </definitions>