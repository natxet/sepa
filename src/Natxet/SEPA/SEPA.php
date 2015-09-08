<?php

namespace Natxet\SEPA;

class SEPA
{

    const TYPE_NUMERIC = 'numeric';

    const TYPE_ALPHANUMERIC = 'alphanumeric';

    const VERSION_CUADERNO = 34145;

    const IBAN_TYPE = 'A';

    protected $file = '';

    /**
     * @var \StdClass
     */
    protected $data;

    /**
     * @var bool throw exceptions in case of a mandatory field empty
     */
    public $strict_mode = true;

    /**
     * @var int sumatory of amount (no decimals)
     */
    protected $total_transferencias_importe = 0;

    /**
     * @var int counter of registries
     */
    protected $total_transferencias_registros = 0;

    /**
     * @param \StdClass $data
     */
    public function __construct( \StdClass $data )
    {
        $this->data = $data;

        if (!property_exists( $this->data, 'ordenante' )) {
            throw new \InvalidArgumentException("Must define a ordenante item in data");
        }

        if (!property_exists( $this->data, 'beneficiarios' )) {
            throw new \InvalidArgumentException("Must define a beneficiarios item in data");
        }

        return $this;
    }

    public function output()
    {
        $this->addCabeceraOrdenante();
        $this->addCabeceraTransferencia();
        foreach($this->data->beneficiarios as $beneficiario)
        {
            $this->addRegistroBeneficiario($beneficiario);
        }
        $this->addTotalTransferencia();
        $this->addTotalGeneral();

        return $this->encodeToIso( $this->file );
    }

    public function encodeToIso( $string )
    {
        return mb_convert_encoding( $string, 'ISO-8859-1', 'UTF-8', true );
    }

    /**
     * @param \StdClass $object
     * @param string $property name of the property to extract
     * @param bool $mandatory must throw exception if fails
     *
     * @return mixed
     */
    protected function getObjectData( \StdClass $object, $property, $mandatory = true )
    {
        if (property_exists( $object, $property )) {
            return $object->$property;
        }
        elseif($this->strict_mode && $mandatory) {
            throw new \InvalidArgumentException("Mandatory field $property is missing");
        }
    }

    /**
     * @param string $property name of the property to extract
     * @param bool $mandatory must throw exception if fails
     *
     * @return mixed
     */
    protected function getOrdenanteData( $property, $mandatory = true )
    {
        return $this->getObjectData($this->data->ordenante, $property, $mandatory);
    }

    protected function addCabeceraOrdenante()
    {
        if($fecha_creacion = $this->getOrdenanteData( 'fecha_creacion', false )){
            $fecha_creacion = $this->formatDate($fecha_creacion);
        }
        else {
            $fecha_creacion = date( 'Ymd' );
        }

        if($fecha_ejecucion = $this->getOrdenanteData( 'fecha_ejecucion', false )){
            $fecha_ejecucion = $this->formatDate($fecha_ejecucion);
        }
        else {
            $fecha_ejecucion = date( 'Ymd', strtotime( "+3 days" ) );
        }

        $detalle_cargo = $this->getOrdenanteData( 'detalle_cargo' ) ? 1 : 0;

        $this->addField( 1, 2 ); // codigo_registro
        $this->addField( 'ORD', 3, self::TYPE_ALPHANUMERIC ); // codigo_operacion
        $this->addField( self::VERSION_CUADERNO, 5 ); // versión cuaderno
        $this->addField( 1, 3 ); // numero_dato
        $this->addField( $this->getOrdenanteData( 'nif_ordenante' ), 9, self::TYPE_ALPHANUMERIC );
        $this->addField( $this->getOrdenanteData( 'sufijo_ordenante' ), 3, self::TYPE_ALPHANUMERIC );
        $this->addField( $fecha_creacion, 8 );
        $this->addField( $fecha_ejecucion, 8 );
        $this->addField( self::IBAN_TYPE, 1, self::TYPE_ALPHANUMERIC ); // identificador_cuenta_ordenante A:IBAN
        $this->addField( $this->getOrdenanteData( 'iban_ordenante' ), 34, self::TYPE_ALPHANUMERIC );
        $this->addField( $detalle_cargo, 1 );
        $this->addField( $this->getOrdenanteData( 'nombre_ordenante' ), 70, self::TYPE_ALPHANUMERIC );
        $this->addField( $this->getOrdenanteData( 'direccion_via_y_numero' ), 50, self::TYPE_ALPHANUMERIC );
        $this->addField( $this->getOrdenanteData( 'direccion_cp_y_poblacion' ), 50, self::TYPE_ALPHANUMERIC );
        $this->addField( $this->getOrdenanteData( 'direccion_provincia' ), 40, self::TYPE_ALPHANUMERIC );
        $this->addField( $this->getOrdenanteData( 'pais_ordenante' ), 2, self::TYPE_ALPHANUMERIC );
        $this->addField( '', 311, self::TYPE_ALPHANUMERIC ); // libre: leave blank!

        $this->addLine();
    }

    protected function addCabeceraTransferencia()
    {
        $this->addField( 2, 2 ); // codigo_registro
        $this->addField( 'SCT', 3, self::TYPE_ALPHANUMERIC ); // codigo_operacion
        $this->addField( self::VERSION_CUADERNO, 5 ); // versión cuaderno
        $this->addField( $this->getOrdenanteData( 'nif_ordenante' ), 9, self::TYPE_ALPHANUMERIC );
        $this->addField( $this->getOrdenanteData( 'sufijo_ordenante' ), 3, self::TYPE_ALPHANUMERIC );
        $this->addField( '', 578, self::TYPE_ALPHANUMERIC ); // libre: leave blank!

        $this->addLine();
    }

    /**
     * @param \StdClass $b Beneficiario
     */
    protected function addRegistroBeneficiario( \StdClass $b )
    {
        $importe_transferencia = $this->formatCurrency( $this->getObjectData( $b, 'importe_transferencia' ) );
        if (strlen( $importe_transferencia ) > 11) {
            throw new \InvalidArgumentException( 'Exceeded maximum number of digits (11) for importe_transferencia' );
        }
        $this->total_transferencias_importe += $importe_transferencia;
        $this->total_transferencias_registros++;

        $tipo_transferencia = $this->getObjectData( $b, 'tipo_transferencia', false );
        if(!$tipo_transferencia) $tipo_transferencia = 'SUPP';

        $proposito_transferencia = $this->getObjectData( $b, 'proposito_transferencia', false );
        if(!$proposito_transferencia) $proposito_transferencia = 'SUPP';

        $this->addField( 3, 2 ); // codigo_registro
        $this->addField( 'SCT', 3, self::TYPE_ALPHANUMERIC ); // codigo_operacion
        $this->addField( self::VERSION_CUADERNO, 5 ); // versión cuaderno
        $this->addField( 2, 3 ); // numero_dato
        $this->addField( $this->getObjectData( $b, 'referencia_ordenante' ), 35, self::TYPE_ALPHANUMERIC );
        $this->addField( self::IBAN_TYPE, 1, self::TYPE_ALPHANUMERIC ); // identificador_cuenta_beneficiario A:IBAN
        $this->addField( $this->getObjectData( $b, 'iban_beneficiario' ), 34, self::TYPE_ALPHANUMERIC );
        $this->addField( $importe_transferencia, 11);
        $this->addField( 3, 1 ); // clave_gastos (3: shared expenses)
        $this->addField( $this->getObjectData( $b, 'bic_beneficiario' ), 11, self::TYPE_ALPHANUMERIC );
        $this->addField( $this->getObjectData( $b, 'nombre_beneficiario' ), 70, self::TYPE_ALPHANUMERIC );
        $this->addField( $this->getObjectData( $b, 'direccion_via_y_numero' ), 50, self::TYPE_ALPHANUMERIC );
        $this->addField( $this->getObjectData( $b, 'direccion_cp_y_poblacion' ), 50, self::TYPE_ALPHANUMERIC );
        $this->addField( $this->getObjectData( $b, 'direccion_provincia' ), 40, self::TYPE_ALPHANUMERIC );
        $this->addField( $this->getObjectData( $b, 'pais_beneficiario' ), 2, self::TYPE_ALPHANUMERIC );
        $this->addField( $this->getObjectData( $b, 'concepto' ), 140, self::TYPE_ALPHANUMERIC );
        $this->addField( '', 35, self::TYPE_ALPHANUMERIC ); // identificacion_instruccion. leave blank!
        $this->addField( $tipo_transferencia, 4, self::TYPE_ALPHANUMERIC );
        $this->addField( $proposito_transferencia, 4, self::TYPE_ALPHANUMERIC );
        $this->addField( '', 99, self::TYPE_ALPHANUMERIC ); // libre. leave blank!

        $this->addLine();
    }

    protected function addTotalTransferencia()
    {
        if (strlen( $this->total_transferencias_importe ) > 17) {
            throw new \InvalidArgumentException(
                'Exceeded maximum number of digits (17) for total_transferencias_importe'
            );
        }

        if (strlen( $this->total_transferencias_registros ) > 8) {
            throw new \InvalidArgumentException(
                'Exceeded maximum number of digits (17) for total_transferencias_registros'
            );
        }

        // count of numero_dato = 2 + header trans + totals trans (+ optional fields)
        $total_registros = $this->total_transferencias_registros + 2;

        $this->addField( 4, 2 ); // codigo_registro
        $this->addField( 'SCT', 3, self::TYPE_ALPHANUMERIC ); // codigo_operacion
        $this->addField( $this->total_transferencias_importe, 17 );
        $this->addField( $this->total_transferencias_registros, 8 );
        $this->addField( $total_registros, 10 );
        $this->addField( '', 560, self::TYPE_ALPHANUMERIC ); // libre. leave blank!

        $this->addLine();
    }

    protected function addTotalGeneral()
    {
        // count of numero_dato = 2 + header trans + totals trans (+ optional fields) + header general + total general
        $total_registros = $this->total_transferencias_registros + 4;

        $this->addField( 99, 2 ); // codigo_registro
        $this->addField( 'ORD', 3, self::TYPE_ALPHANUMERIC ); // codigo_operacion
        $this->addField( $this->total_transferencias_importe, 17 );
        $this->addField( $this->total_transferencias_registros, 8 );
        $this->addField( $total_registros, 10 );
        $this->addField( '', 560, self::TYPE_ALPHANUMERIC ); // libre. leave blank!

        $this->addLine();
    }

    protected function addLine()
    {
        $this->file .= "\n";
    }

    protected function addField( $value, $size, $type = self::TYPE_NUMERIC )
    {
        if (mb_strlen( $value ) > $size) {
            $this->file .= substr( $value, 0, $size );
            return;
        }

        if (self::TYPE_NUMERIC === $type) {
            $this->file .= str_pad( $value, $size, '0', STR_PAD_LEFT );
        } else {
            $this->file .= str_pad( $this->clean_string( $value ), $size, ' ', STR_PAD_RIGHT );
        }
    }


    /**
     * @param string $date ISO date YYYY-MM-DD
     *
     * @return string date YYYYMMDD
     */
    protected function formatDate( $date )
    {
        return str_replace( '-', '', $date );
    }

    /**
     * @param float $amount the amount to convert f.i. '14.52' OR '14.1' OR '14'
     *
     * @return string 1452 (multiplied by 100)
     */
    protected function formatCurrency( $amount )
    {
        return (string) $amount * 100;
    }

    protected function clean_string( $string )
    {
        $accents_regex = '~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml);~i';
        $special_cases = array(
            '&' => 'and',
            "'" => '',
            'Ñ' => 'N',
            'ñ' => 'n',
            'Ç' => 'C',
            'ç' => 'c',
            'º' => 'o',
            'ª' => 'a'
        );

        $string        = trim( $string );
        $string        = str_replace( array_keys( $special_cases ), array_values( $special_cases ), $string );
        $string        = preg_replace( $accents_regex, '$1', htmlentities( $string, ENT_QUOTES, 'UTF-8' ) );
        $string        = preg_replace( '|[^A-Za-z0-9/\-\?\:\(\)\.\,\+ ]|u', '', $string );

        return $string;
    }
}
