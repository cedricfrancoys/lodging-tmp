<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\finance\accounting;

class InvoiceLineGroup extends \finance\accounting\InvoiceLineGroup {

    public static function getColumns() {
        return [

            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => Invoice::getType(),
                'description'       => 'Invoice the line is related to.',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'invoice_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => InvoiceLine::getType(),
                'foreign_field'     => 'invoice_line_group_id',
                'description'       => 'Detailed lines of the group.',
                'ondetach'          => 'delete',
                'onupdate'          => 'onupdateInvoiceLinesIds'
            ]

        ];
    }


    public static function onupdateInvoiceLinesIds($om, $oids, $values, $lang) {
        $groups = $om->read(self::getType(), $oids, ['invoice_id']);
        if($groups) {
            $invoices_ids = [];
            foreach($groups as $gid => $group) {
                $invoices_ids[] = $group['invoice_id'];
            }
            $om->update(Invoice::getType(), $invoices_ids, ['price' => null, 'total' => null]);
        }
    }


}