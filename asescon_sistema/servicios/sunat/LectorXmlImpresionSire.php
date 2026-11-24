<?php
declare(strict_types=1);

final class LectorXmlImpresionSire
{
    public function leer(string $ruta, array $clave, string $receptor): array
    {
        $xml = @file_get_contents($ruta, false, null, 0, 5_000_001);
        if (!is_string($xml) || strlen($xml) > 5_000_000 || !class_exists(DOMDocument::class)) {
            return [];
        }
        $dom = new DOMDocument();
        if (!@$dom->loadXML($xml, LIBXML_NONET) || $dom->doctype !== null || $dom->documentElement?->localName !== 'Invoice'
            || $dom->documentElement?->namespaceURI !== 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2') {
            return [];
        }

        $x = new DOMXPath($dom);
        $x->registerNamespace('b', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $x->registerNamespace('a', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $raiz = $dom->documentElement;
        $leer = static fn(string $ruta, DOMNode $n) => trim((string)$x->evaluate("string($ruta)", $n));
        $emisor = 'a:AccountingSupplierParty/a:Party';
        $cliente = 'a:AccountingCustomerParty/a:Party';
        $idParte = 'a:PartyIdentification/b:ID';
        $id = explode('-', $leer('b:ID', $raiz), 2);
        if ($clave['tipo_documento'] !== '01' || $leer('b:InvoiceTypeCode', $raiz) !== '01'
            || strtoupper($id[0]) !== $clave['serie'] || !isset($id[1])
            || !ctype_digit($id[1]) || (ltrim($id[1], '0') ?: '0') !== $clave['numero']
            || $leer("$emisor/$idParte", $raiz) !== $clave['ruc_emisor']
            || $receptor === '' || $leer("$cliente/$idParte", $raiz) !== $receptor) {
            return [];
        }

        $direccion = static function (string $parte) use ($leer, $raiz): string {
            $ruta = "$parte/a:PartyLegalEntity/a:RegistrationAddress";
            return implode(' - ', array_unique(array_filter(array_map(
                static fn(string $campo): string => $leer("$ruta/$campo", $raiz),
                ['a:AddressLine/b:Line', 'b:StreetName', 'b:District', 'b:CityName', 'b:CountrySubentity']
            ), 'strlen')));
        };
        $importes = [];
        foreach ([
            'descuentos' => 'AllowanceTotalAmount', 'cargos' => 'ChargeTotalAmount',
            'anticipo' => 'PrepaidAmount', 'redondeo' => 'PayableRoundingAmount', 'total' => 'PayableAmount',
        ] as $campo => $etiqueta) {
            $importes[$campo] = $leer("a:LegalMonetaryTotal/b:$etiqueta", $raiz);
        }
        $moneda = $leer('b:DocumentCurrencyCode', $raiz);
        // Los subtotales de otra moneda no deben mezclarse con la moneda del comprobante.
        foreach ($x->query('a:TaxTotal/a:TaxSubtotal', $raiz) as $subtotal) {
            $codigo = $leer('a:TaxCategory/a:TaxScheme/b:ID', $subtotal);
            foreach ([
                '1000' => ['gravado', 'igv'], '9997' => ['exonerado', null],
                '9998' => ['inafecto', null], '9996' => ['gratuito', null],
                '9995' => ['exportacion', null], '2000' => [null, 'isc'],
                '7152' => [null, 'icbper'], '9999' => [null, 'tributos'],
            ][$codigo] ?? [] as $i => $campo) {
                if ($campo === null) continue;
                $tag = $i === 0 ? 'b:TaxableAmount' : 'b:TaxAmount';
                $divisa = $leer("$tag/@currencyID", $subtotal);
                $valor = $leer($tag, $subtotal);
                if (($divisa === '' || $divisa === $moneda) && is_numeric($valor)) {
                    $importes[$campo] = (string)((float)($importes[$campo] ?? 0) + (float)$valor);
                }
            }
        }
        foreach ($x->query('a:AllowanceCharge[b:ChargeIndicator="false" or b:ChargeIndicator="0"]', $raiz) as $descuento) {
            $motivo = $leer('b:AllowanceChargeReasonCode', $descuento);
            $monto = $leer('b:Amount', $descuento);
            if (in_array($motivo, ['02', '03'], true) && is_numeric($monto)) {
                $campo = $motivo === '02' ? 'descuento_gravado' : 'descuento_no_gravado';
                $importes[$campo] = (string)((float)($importes[$campo] ?? 0) + (float)$monto);
            }
        }
        $items = [];
        foreach ($x->query('a:InvoiceLine', $raiz) as $linea) {
            $descripcion = [];
            foreach ($x->query('a:Item/b:Description', $linea) as $texto) {
                $descripcion[] = trim($texto->textContent);
            }
            $items[] = [
                $leer('b:InvoicedQuantity', $linea), $leer('b:InvoicedQuantity/@unitCode', $linea),
                $leer('a:Item/a:SellersItemIdentification/b:ID', $linea),
                implode(' ', $descripcion), $leer('a:Price/b:PriceAmount', $linea),
                $leer('a:TaxTotal/a:TaxSubtotal[a:TaxCategory/a:TaxScheme/b:ID="7152"]/b:TaxAmount', $linea),
            ];
        }
        $notas = [];
        foreach ($x->query('b:Note[not(@languageLocaleID="1000")]', $raiz) as $nota) {
            $notas[] = trim($nota->textContent);
        }

        return [
            'nombre_comercial' => $leer("$emisor/a:PartyName/b:Name", $raiz),
            'emisor' => $leer("$emisor/a:PartyLegalEntity/b:RegistrationName", $raiz),
            'direccion_emisor' => $direccion($emisor),
            'receptor' => $leer("$cliente/a:PartyLegalEntity/b:RegistrationName", $raiz),
            'direccion_receptor' => $direccion($cliente),
            'fecha' => $leer('b:IssueDate', $raiz),
            'moneda' => $moneda,
            'forma_pago' => $leer('a:PaymentTerms[b:ID="FormaPago"]/b:PaymentMeansID', $raiz),
            'observaciones' => implode(' ', array_filter($notas, 'strlen')),
            'total_letras' => $leer('b:Note[@languageLocaleID="1000"]', $raiz),
            'items' => $items,
            'importes' => array_filter($importes, 'is_numeric'),
        ];
    }
}
