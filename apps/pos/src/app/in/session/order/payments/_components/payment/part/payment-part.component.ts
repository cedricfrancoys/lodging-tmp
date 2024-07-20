import { Component, OnInit, AfterViewInit, Input, Output, EventEmitter, ChangeDetectorRef, SimpleChanges } from '@angular/core';
import { FormControl } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';

import { ApiService, ContextService, TreeComponent } from 'sb-shared-lib';
import { OrderPayment } from '../../../_models/payment.model';
import { Order } from '../../../_models/order.model';
import { OrderPaymentPart } from '../../../_models/payment-part.model';
import { Customer } from '../../../_models/customer.model';

// declaration of the interface for the map associating relational Model fields with their components
interface OrderPaymentPartComponentsMap {
    // no sub-items
};

@Component({
    selector: 'session-order-payments-payment-part',
    templateUrl: 'payment-part.component.html',
    styleUrls: ['payment-part.component.scss']
})
export class SessionOrderPaymentsPaymentPartComponent extends TreeComponent<OrderPaymentPart, OrderPaymentPartComponentsMap> implements OnInit, AfterViewInit  {
    // server-model relayed by parent
    @Input() set model(values: any) { this.update(values) }

    @Input() customer: Customer;
    @Input() payment: OrderPayment;
    @Input() order: Order;

    @Output() updated = new EventEmitter();
    @Output() deleted = new EventEmitter();

    public ready: boolean = false;


    public amount: FormControl = new FormControl();
    public maxAmount: number = null;
    public initialAmount: number = 0;

    public voucher_ref:FormControl = new FormControl();

    public allowedPaymentMethods: string[] = [];
    public canValidate: boolean = true;

    public get partLabel():string {
        const map: any = {
            "cash":         "espèces",
            "bank_card":    "carte",
            "booking":      "réservation",
            "voucher":      "voucher"
        };
        const value = this.instance.payment_method;
        return map.hasOwnProperty(value)?map[value]:'montant';
    }

    constructor(
        private router: Router,
        private route: ActivatedRoute,
        private cd: ChangeDetectorRef,
        private api: ApiService,
        private context: ContextService
    ) {
        super( new OrderPaymentPart() )
    }


    public ngAfterViewInit() {
        this.componentsMap = {};
    }

    public async ngOnChanges(changes: SimpleChanges) {
        if(changes.hasOwnProperty('model')) {
            // default to due_amount
            if(this.instance.amount == 0) {
                this.amount.setValue(this.payment.total_due-this.payment.total_paid);
            }
            this.canValidate = this.calcCanValidate();
            this.ready = true;
        }
    }

    public async ngOnInit() {
        this.initialAmount = this.instance.amount;

        this.amount.valueChanges.subscribe((value: number) => {
            this.instance.amount = value;
            this.canValidate = this.calcCanValidate();
        });

        if(this.payment.total_due < 0) {
            this.amount.disable();
        }

        this.voucher_ref.valueChanges.subscribe( (value:number)  => this.instance.voucher_ref = value );

        this.allowedPaymentMethods = this.getAllowedPaymentMethod();
    }

    public update(values:any) {
        super.update(values);

        // update widgets and sub-components, if necessary
        this.amount.setValue(this.instance.amount);

        this.voucher_ref.setValue(this.instance.voucher_ref);
    }

    public async onclickDelete() {
        await this.api.update((new OrderPayment()).entity, [this.instance.order_payment_id], {order_payment_parts_ids: [-this.instance.id]});
        await this.api.remove(this.instance.entity, [this.instance.id]);
        this.deleted.emit();
    }

    public async onchangeBookingId(booking: any) {
        if(booking.hasOwnProperty('id')) {
            this.instance.booking_id = booking.id;
            await this.api.update((new OrderPaymentPart()).entity, [this.instance.id], {booking_id: booking.id, payment_method: 'booking'});
            if(booking.customer_id && booking.customer_id.id) {
                await this.api.update((new Order()).entity, [this.payment.order_id], {customer_id: booking.customer_id.id});
            }
            this.updated.emit();
        }
    }

    public async onValidate() {
        try {
            let values:any = {
                amount: this.instance.amount,
                payment_method: this.instance.payment_method,
                status: 'paid'
            };

            if(this.instance.payment_method == 'booking') {
                values.amount = this.payment.total_due;
            }
            if(typeof this.instance.booking_id != 'object' && this.instance.booking_id > 0) {
                values.booking_id = this.instance.booking_id;
            }
            if(typeof this.instance.voucher_ref != 'object' && this.instance.voucher_ref > 0) {
                values.voucher_ref = this.instance.voucher_ref;
            }

            await this.api.update(this.instance.entity, [this.instance.id], values);
            this.updated.emit();
        }
        catch(response) {
            console.log('unexpected error', response);
        }
    }

    public changePaymentMethod(paymentMethod: string) {
        this.instance.payment_method = paymentMethod;
        this.maxAmount = paymentMethod === 'bank_card' ? this.initialAmount : null;

        this.canValidate = this.calcCanValidate();
    }

    public hasFunding() {
        return this.payment?.has_funding;
    }

    public getAllowedPaymentMethod(): string[] {
        if(this.payment.total_due < 0) {
            return ['cash'];
        }

        const paymentParts = this.payment.order_payment_parts_ids;
        if(!paymentParts.length || paymentParts[0].status !== 'paid') {
            return ['cash', 'bank_card', 'booking'];
        }

        return paymentParts[0].payment_method === 'booking' ?
            ['booking'] :
            ['cash', 'bank_card'];
    }

    private calcCanValidate() {
        return this.instance.amount !== null
            && (
                this.instance.payment_method !== 'booking'
                || this.instance.booking_id
            )
            && (
                this.instance.payment_method !== 'bank_card'
                || this.instance.amount <= this.maxAmount
            );
    }
}
