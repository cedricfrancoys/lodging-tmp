import { Component, OnInit, AfterViewInit, Input, Output, EventEmitter, ChangeDetectorRef, ViewChildren, QueryList, Inject } from '@angular/core';
import { FormControl } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { ApiService, ContextService, TreeComponent } from 'sb-shared-lib';
import { Order } from '../../_models/order.model';
import { OrderPayment } from '../../_models/payment.model';
import { OrderPaymentPart } from '../../_models/payment-part.model';
import { Customer } from '../../_models/customer.model';
import { SessionOrderPaymentsPaymentPartComponent } from './part/payment-part.component';
import { SessionOrderPaymentsOrderLineComponent } from './line/order-line.component';
import { MatDialog, MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';


// declaration of the interface for the map associating relational Model fields with their components
interface OrderPaymentComponentsMap {
    order_payment_parts_ids: QueryList<SessionOrderPaymentsPaymentPartComponent>
    order_lines_ids: QueryList<SessionOrderPaymentsOrderLineComponent>
};

@Component({
    selector: 'session-order-payments-order-payment',
    templateUrl: 'order-payment.component.html',
    styleUrls: ['order-payment.component.scss']
})
export class SessionOrderPaymentsOrderPaymentComponent extends TreeComponent<OrderPayment, OrderPaymentComponentsMap> implements OnInit, AfterViewInit  {
    // servel-model relayed by parent
    @Input() set model(values: any) { this.update(values) }
    @Input() customer: Customer;
    @Input() order: Order;
    @Input() areAllOrderLinesAssigned: boolean;

    @Output() updated = new EventEmitter();
    @Output() deleted = new EventEmitter();
    @Output() validated = new EventEmitter();
    @Output() updatedQty = new EventEmitter();
    @Output() selectedPaymentPartIndex = new EventEmitter();
    @Output() displayPaymentProducts = new EventEmitter();

    @ViewChildren(SessionOrderPaymentsPaymentPartComponent) sessionOrderPaymentsPaymentPartComponents: QueryList<SessionOrderPaymentsPaymentPartComponent>;
    @ViewChildren(SessionOrderPaymentsOrderLineComponent) sessionOrderPaymentsOrderLineComponents: QueryList<SessionOrderPaymentsOrderLineComponent>;


    public ready: boolean = false;

    public display = "";
    public index : number = 0;
    public focused: any;
    public line_quantity : any = "";


    constructor(
        private router: Router,
        private route: ActivatedRoute,
        private cd: ChangeDetectorRef,
        private api: ApiService,
        private context: ContextService,
        private dialog: MatDialog
    ) {
        super( new OrderPayment() )
    }


    public ngAfterViewInit() {
        // init local componentsMap
        let map:OrderPaymentComponentsMap = {
            order_payment_parts_ids: this.sessionOrderPaymentsPaymentPartComponents,
            order_lines_ids: this.sessionOrderPaymentsOrderLineComponents
        };
        this.componentsMap = map;
    }

    public ngOnInit() {
        // this.line_quantity.valueChanges.subscribe( (value:number)  => console.log('okay') );
    }

    public update(values:any) {
        super.update(values);
    }

    public getPaymentParts() {
        let payment_parts_ids = [...this.instance.order_payment_parts_ids];
        return payment_parts_ids.reverse();
    }

    public getPaymentPart() : SessionOrderPaymentsPaymentPartComponent {
        let children = this.sessionOrderPaymentsPaymentPartComponents.toArray();
        return children[this.index];
    }

    public hasNotValidPaymentPart(): boolean {
        const paymentPart = this.getPaymentPart();

        return paymentPart
            && paymentPart.instance.status == 'pending'
            && !paymentPart.canValidate;
    }

    public canAddPart() {
        const absTotalDue = Math.abs(this.instance.total_due);
        const absTotalPaid = Math.abs(this.instance.total_paid);

        return absTotalDue > 0
            && absTotalPaid < absTotalDue
            && this.instance.status != 'paid';
    }

    public canValidate() {
        const absTotalDue = Math.abs(this.instance.total_due);
        const absTotalPaid = Math.abs(this.instance.total_paid);

        return absTotalDue > 0
            && absTotalPaid >= absTotalDue
            && this.instance.status != 'paid'
            && this.areAllOrderLinesAssigned;
    }

    public calcDueRemaining() {
        return Math.max(0, this.instance.total_due - this.instance.total_paid);
    }

    public calcReturnedAmount() {
        return Math.max(0, this.instance.total_paid - this.instance.total_due);
    }

    public async onclickDelete() {
        await this.api.update((new Order()).entity, [this.instance.order_id], {order_payments_ids: [-this.instance.id]});
        this.deleted.emit();
    }

    /**
     * User clicked on the validation button of a payment part
     * @param part_id
     */
    public async onupdatePart(part_id:number) {
        // relay to parent component
        this.updated.emit();
    }

    public async ondeletePart(part_id:number) {
        // relay to parent component
        this.updated.emit();
    }

    public async ondeleteLine(line_id:number) {
        try {
            await this.api.update(this.instance.entity, [this.instance.id], {order_lines_ids: [-line_id]});
            this.instance.order_lines_ids.splice(this.instance.order_lines_ids.findIndex((e:any)=>e.id == line_id), 1);
        }
        catch(response) {
            console.log('unexpected error', response);

        }
    }

    public async onclickAddPart() {
        if(this.hasNotValidPaymentPart()) {
            return;
        }

        if(this.instance.total_due < 0) {
            await this.getPaymentPart().onValidate();
            return;
        }

        try {
            // if there is an active payment part being edited, validate it
            let amount_remaining: number = this.calcDueRemaining();
            let paymentPart = this.getPaymentPart();
            if(paymentPart && paymentPart.instance.status != 'paid') {
                if(paymentPart.instance.payment_method === 'booking') {
                    amount_remaining = 0;
                }
                else {
                    amount_remaining -= paymentPart.instance.amount;
                }

                await paymentPart.onValidate();
            }
            if(amount_remaining > 0) {
                await this.api.create((new OrderPaymentPart()).entity, {order_payment_id: this.instance.id, amount: amount_remaining});
                this.updated.emit();
            }
        }
        catch(response) {
            console.log('unexpected error', response);
        }
    }

    public async onclickConfirmPayment() {
        if(this.hasNotValidPaymentPart()) {
            return;
        }

        try {
            // mark all payment parts as paid (cannot be reversed)
            await this.api.update((new OrderPaymentPart).entity, this.instance.order_payment_parts_ids.map((a:any) => a.id), {status: 'paid'});
            let paymentPart = this.getPaymentPart();
            if(paymentPart && paymentPart.instance.status != 'paid') {
                paymentPart.onValidate();
            }
            else {
                // delegate payment validation to parent component
                this.validated.emit();
            }
        }
        catch(response) {
            console.log('unexpected error', response)
        }
    }


    public onDisplayProducts() {
        this.displayPaymentProducts.emit();
    }

    public selectLine(index:number){
        this.index = index;
    }

    public onSelectedPaymentPart(index : number){
        this.selectedPaymentPartIndex.emit(index);
    }

    public async changeQuantity(line : any){
        // Remove the number of elements indicated, and create a new object with the difference
        if(parseInt(this.line_quantity) < line.qty) {
            await this.api.create('lodging\\sale\\pos\\OrderLine', {
                order_id: line.order_id,
                order_payment_id: 0,
                order_unit_price: line.unit_price,
                has_funding: line.has_funding,
                funding_id: line.funding_id,
                vat_rate: line.vat_rate,
                discount: line.discount,
                free_qty: line.free_qty,
                name: line.name,
                qty: line.qty-parseInt(this.line_quantity)
            });
            await this.api.update('lodging\\sale\\pos\\OrderLine', [line.id], {
                qty : parseInt(this.line_quantity)
            });
        }else{
            await this.api.update('lodging\\sale\\pos\\OrderLine', [line.id], {
                qty : parseInt(this.line_quantity)
            });
        }
        line.qty = this.line_quantity;
        this.updatedQty.emit();
    }
}
