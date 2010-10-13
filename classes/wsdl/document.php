<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Easy creation of WSDL documents
 *
 * @package    Wsdl
 * @category   Core
 * @author     Michal Kocian <michal@kocian.name>
 * @copyright  (c) 2010 Michal Kocian
 */
class Wsdl_Document {

    /**
     * @var SimpleXMLElement Výsledný WDSL dokument
     */
    private $wsdl = NULL;

    /**
     * @var string The resulting WSDL document
     */
    private $name = NULL;

    /**
     * @var array Classes to parse
     */
    private $classes = array();

    /**
     * @var array Methods informations
     */
    private $items = array();

    /**
     * @var bool Modified input
     */
    private $modified = TRUE;

    /**
     * Add class for processing
     *
     * @param array $class Class name
     */
    public function add_class(array $class) {

        foreach ($class as $class => $uri) {
            $this->classes[$class] = $uri;
        }

        $this->modified = TRUE;
    }

    /**
     * Parse a PHPDoc comment for all parameters
     *
     * @param string $comment PHPDoc comment
     * @return array Parsed comments
     */
    private function parse_comment($comment) {

        // Normalize all new lines to \n
        $comment = str_replace(array("\r\n", "\n"), "\n", $comment);

        // Remove the PHPDoc open/close tags and split
        $comment = array_slice(explode("\n", $comment), 1, -1);

        // Tag content
        $tags = array();

        foreach ($comment as $i => $line) {

            // Remove all leading whitespace
            $line = preg_replace('/^\s*\* ?/m', '', $line);

            // Search this line for a tag
            if (preg_match('/^@(\S+)(?:\s*(.*))?$/', $line, $matches)) {

                // This is a tag line
                unset($comment[$i]);

                $name = $matches[1];
                $text = isset($matches[2]) ? $matches[2] : '';

                switch ($name) {
                    case 'param':
                        preg_match('/^(\S+)\s+\$(\S+)\s+(.*)$/', $text, $matches);

                        // Add the tag
                        switch ($matches[1]) {
                            case 'int':
                            case 'float':
                            case 'string':
                                $type = 'xsd:'.$matches[1];
                                break;
                            case 'integer':
                                $type = 'xsd:int';
                                break;
                            case 'array':
                                $type = 'soapenc:Array';
                                break;

                            default:
                                $type = 'xsd:'.$matches[1];
                                break;
                        }

                        // Add a new tag
                        $tags[$name][$matches[2]] = array(
                            'type' => $type,
                            'doc' => isset($matches[3]) ? $matches[3] : NULL,
                        );

                        break;
                    case 'return':

                        preg_match('/^(\S+)(?:\s(.*))?$/', $text, $matches);

                        // Add the tag
                        $tags[$name] = array(
                            'type' => $matches[1],
                            'doc' => isset($matches[2]) ? $matches[2] : NULL,
                        );
                        break;
                }
            } else {

                // Overwrite the comment line
                $comment[$i] = (string) $line;
            }
        }

        // Concat the comment lines back to a block of text
        $tags['doc'] = trim(implode("\n", $comment));

        return $tags;
    }

    /**
     * Find all methods and parse their comments
     *
     * @return array
     */
    private function parse_comments() {

        // Process all classes
        foreach ($this->classes as $class => $uri) {

            // Check class
            if ( ! class_exists($class)) {

                // Class doesn't exist
                throw new Wsdl_Exception('Class "'.$class.'" doesn\'t exist!');
            }

            $class = new ReflectionClass($class);

            // Find all methods
            foreach ($class->getMethods() as $method) {

                // Include only public class
                if ($method->isPublic()) {

                    // Parse PHPDoc
                    $this->items[$class->getName()][$method->getName()] = $this->parse_comment($method->getDocComment());
                }
            }
        }

        return $this->items;
    }

    /**
     * Set WSDL document name
     *
     * @param string $name
     */
    public function set_name($name) {

        $this->name = $name;

        $this->modified = TRUE;
    }

    /**
     * Create new WSDL document
     *
     * @return bool
     */
    private function create() {

        // Find all methods and parse comments
        $this->parse_comments();

        // Create new element
        $this->wsdl = new SimpleXMLElement('<definitions name="'.$this->name.'" targetNamespace="urn:'.$this->name.'" xmlns:typens="urn:'.$this->name.'" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/" xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/" xmlns:wsdl="http://schemas.xmlsoap.org/wsdl/" xmlns="http://schemas.xmlsoap.org/wsdl/" />', NULL, FALSE, 'wsdl');

        // Create all messages
        $this->create_message();

        // Create port types
        $this->create_port_types();

        // Create binding
        $this->create_binding();

        // Create service
        $this->create_service();

        $this->modified = FALSE;

        return TRUE;
    }

    /**
     * Save WSDL document
     *
     * @param bool $filename
     * @return bool
     */
    public function save($filename) {

        if ($this->modified) {

            // create document
            $this->create();
        }

        if ( ! is_writable($filename)) {

            // file is not writable
            throw new Wsdl_Exception('Can not write file `'.$filename.'`');
        }

        // save WSDL document
        return (bool) file_put_contents($filename, $this->wsdl->asXML());
    }

    /**
     * Get WSDL document
     *
     * @return string
     */
    public function get_document() {

        if ($this->modified) {

            // create document
            $this->create();
        }

        return $this->wsdl->asXML();
    }

    /**
     * Validate this WSDL document
     *
     * @return bool
     */
    public function validate() {

        if ($this->modified) {

            // create document
            $this->create();
        }

        $doc = new DomDocument;

        // find xml schema
        $xmlschema = Kohana::find_file('views', 'schema.xsd');

        // Load the xml document
        $doc->loadXML($this->wsdl->asXML());

        // Validate the XML file against the schema
        return $doc->schemaValidate($xmlschema);
    }

    /**
     * Create types
     */
    private function create_types() {

        $this->wsdl->addChild('types');
    }

    /**
     * Create messages
     */
    private function create_message() {
        
        foreach ($this->items as $class => $methods) {

            foreach ($methods as $method => $params) {

                    // Creata input message
                    $message = $this->wsdl->addChild('message');
                    $message->addAttribute('name', $method);

                    // Create params
                    foreach ($params['param'] as $param_name => $param) {

                        $part = $message->addChild('part');
                        $part->addAttribute('name', $param_name);
                        $part->addAttribute('type', $this->items[$class][$method]['param'][$param_name]['type']);
                    }

                    // Create response message only if PHPDoc comment @return exists
                    if (isset($this->items[$class][$method]['return'])) {

                        // Create response message
                        $message_response = $this->wsdl->addChild('message');
                        $message_response->addAttribute('name', $method.'Response');
                        $part_response = $message_response->addChild('part');
                        $part_response->addAttribute('name', $method.'Return');
                        $part_response->addAttribute('type', 'xsd:'.$this->items[$class][$method]['return']['type']);
                    }
            }
        }
    }

    /**
     * Create port types
     */
    private function create_port_types() {

        // Create portType for every class
        foreach ($this->items as $class => $methods) {

            $portType = $this->wsdl->addChild('portType');
            $portType->addAttribute('name', $class.'PortType');

            // Create operations
            foreach ($methods as $method => $params) {

                $operation = $portType->addChild('operation');
                $operation->addAttribute('name', $method);

                // Add a documentation
                $documentation = $operation->addChild('documentation', $params['doc']);

                $input = $operation->addChild('input');
                $input->addAttribute('message', 'typens:'.$method);

                // Add output only if PHPDoc comment @return exists
                if (isset($this->items[$class][$method]['return'])) {

                    // Create output
                    $output = $operation->addChild('output');
                    $output->addAttribute('message', 'typens:'.$method.'Response');
                }
            }
        }
    }

    /**
     * Create binding
     */
    private function create_binding() {

        // Create binding for all classes
        foreach ($this->items as $class => $methods) {

            // Create binding for a class
            $binding = $this->wsdl->addChild('binding');
            $binding->addAttribute('name', $class.'Binding');
            $binding->addAttribute('type', 'typens:'.$class.'PortType');

            $soap_binding = $binding->addChild('soap:binding', NULL, 'http://schemas.xmlsoap.org/wsdl/soap/');
            $soap_binding->addAttribute('style', 'rpc');
            $soap_binding->addAttribute('transport', 'http://schemas.xmlsoap.org/soap/http');

            // Create operations
            foreach ($methods as $method => $params) {

                $operation = $binding->addChild('operation');
                $operation->addAttribute('name', $method);

                $soap_operation = $operation->addChild('soap:operation', NULL, 'http://schemas.xmlsoap.org/wsdl/soap/');
                $soap_operation->addAttribute('soapAction', 'urn:'.$method.'Action');

                // Create input
                $input = $operation->addChild('input');
                $body = $input->addChild('soap:body', NULL, 'http://schemas.xmlsoap.org/wsdl/soap/');
                $body->addAttribute('namespace', 'urn:'.$this->name);
                $body->addAttribute('use', 'encoded');
                $body->addAttribute('encodingStyle', 'http://schemas.xmlsoap.org/soap/encoding/');

                // Add output only if PHPDoc comment @return exists
                if (isset($this->items[$class][$method]['return'])) {

                    // Create output
                    $output = $operation->addChild('output');
                    $body = $output->addChild('soap:body', NULL, 'http://schemas.xmlsoap.org/wsdl/soap/');
                    $body->addAttribute('namespace', 'urn:'.$this->name);
                    $body->addAttribute('use', 'encoded');
                    $body->addAttribute('encodingStyle', 'http://schemas.xmlsoap.org/soap/encoding/');
                }
            }
        }
    }

    /**
     * Create service
     */
    private function create_service() {

        $service = $this->wsdl->addChild('service');
        $service->addAttribute('name', $this->name.'Service');

        // Create ports for all classes
        foreach ($this->items as $class => $methods) {

            $port = $service->addChild('port');
            $port->addAttribute('name', $class.'Port');
            $port->addAttribute('binding', 'typens:'.$class.'Binding');

            // Uri for this service
            $address = $port->addChild('soap:address', NULL, 'http://schemas.xmlsoap.org/wsdl/soap/');
            $address->addAttribute('location', $this->classes[$class]);
        }
    }
}