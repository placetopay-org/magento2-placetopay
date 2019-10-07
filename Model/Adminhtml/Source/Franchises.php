<?php

namespace PlacetoPay\Payments\Model\Adminhtml\Source;

/**
 * Class Franchises.
 */
class Franchises
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'CR_VS',
                'label' => 'Visa'
            ],
            [
                'value' => 'CR_CR',
                'label' => 'Credencial Banco de Occidente'
            ],
            [
                'value' => 'CR_VE',
                'label' => 'Visa Electron'
            ],
            [
                'value' => 'CR_DN',
                'label' => 'Diners Club'
            ],
            [
                'value' => 'CR_AM',
                'label' => 'American Express'
            ],
            [
                'value' => 'RM_MC',
                'label' => 'MasterCard'

            ],
            [
                'value' => 'TY_EX',
                'label' => 'Tarjeta Éxito'
            ],
            [
                'value' => 'TY_AK',
                'label' => 'Alkosto'
            ],
            [
                'value' => '_PSE_',
                'label' => 'Débito a cuentas corrientes y ahorros (PSE)'
            ],
            [
                'value' => 'SFPAY',
                'label' => 'Safety Pay'
            ],
            [
                'value' => '_ATH_',
                'label' => 'Corresponsales bancarios Grupo Aval'
            ],
            [
                'value' => 'AC_WU',
                'label' => 'Western Union'
            ],
            [
                'value' => 'PYPAL',
                'label' => 'PayPal'
            ],
            [
                'value' => 'T1_BC',
                'label' => 'Bancolombia Recaudos'
            ],
            [
                'value' => 'AV_BO',
                'label' => 'Banco de Occidente Recaudos'
            ],
            [
                'value' => 'AV_AV',
                'label' => 'Banco AV Villas Recaudos'
            ],
            [
                'value' => 'AV_BB',
                'label' => 'Banco de Bogotá Recaudos'
            ],
            [
                'value' => 'VISAC',
                'label' => 'Visa Checkout'
            ],
            [
                'value' => 'GNPIN',
                'label' => 'GanaPIN'
            ],
            [
                'value' => 'GNRIS',
                'label' => 'Tarjeta RIS'
            ],
            [
                'value' => 'MSTRP',
                'label' => 'Masterpass'
            ],
            [
                'value' => 'DBTAC',
                'label' => 'Registro cuentas débito'
            ],
            [
                'value' => '_PPD_',
                'label' => 'Débito pre-autorizado (PPD)'
            ],
            [
                'value' => 'CR_DS',
                'label' => 'Discover'
            ],
            [
                'value' => 'EFCTY',
                'label' => 'Efecty'
            ],
        ];
    }
}