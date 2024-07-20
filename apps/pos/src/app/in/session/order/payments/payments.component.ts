import { Component, OnInit, AfterViewInit, ChangeDetectorRef, ViewChildren, QueryList, Input, SimpleChanges, ViewChild, ElementRef } from '@angular/core';
import { ActivatedRoute, BaseRouteReuseStrategy, Router } from '@angular/router';
import { ApiService, ContextService, TreeComponent, RootTreeComponent } from 'sb-shared-lib';
import { CashdeskSession } from '../../_models/session.model';
import { Order } from './_models/order.model';
import { OrderPayment } from './_models/payment.model';
import { OrderPaymentPart } from './_models/payment-part.model';
import { OrderLine } from './_models/order-line.model';
import { SessionOrderPaymentsOrderPaymentComponent } from './_components/payment/order-payment.component';
import { SessionOrderPaymentsPaymentPartComponent } from './_components/payment/part/payment-part.component';


import { MatTableDataSource } from '@angular/material/table';
import { DataSource, SelectionModel } from '@angular/cdk/collections';
import { AppKeypadPaymentComponent } from 'src/app/in/_components/keypad-payment/keypad-payment.component';
import { MatSnackBar } from '@angular/material/snack-bar';


// declaration of the interface for the map associating relational Model fields with their components
interface OrderComponentsMap {
    order_payments_ids: QueryList<SessionOrderPaymentsOrderPaymentComponent>
};


@Component({
    selector: 'session-order-payments',
    templateUrl: 'payments.component.html',
    styleUrls: ['payments.component.scss']
})
export class SessionOrderPaymentsComponent extends TreeComponent<Order, OrderComponentsMap> implements RootTreeComponent, OnInit, AfterViewInit {
    @ViewChildren(SessionOrderPaymentsOrderPaymentComponent) sessionOrderPaymentsOrderPaymentComponents: QueryList<SessionOrderPaymentsOrderPaymentComponent>;
    @ViewChild('keypad') keypad: AppKeypadPaymentComponent;
    @ViewChild('payments') payments: ElementRef;

    public ready: boolean = false;

    public amount: any;
    public digits: any;

    public selectedPaymentIndex: number = 0;

    public show_products: boolean = false;

    public due: number;
    public change: any;
    public session: CashdeskSession = new CashdeskSession();

    public orderLines : OrderLine[];
    public areAllOrderLinesAssigned: boolean = false;

    public orderPayments: OrderPayment[];
    public areAllPaymentsPaid: boolean = false;

    public dataSource: MatTableDataSource<OrderLine>;
    public selection: SelectionModel<OrderLine>;

    constructor(
        private router: Router,
        private route: ActivatedRoute,
        private api: ApiService,
        private context: ContextService,
        private snack: MatSnackBar
    ) {
        super(new Order());
    }

    public ngAfterViewInit() {
        // init local componentsMap
        let map: OrderComponentsMap = {
            order_payments_ids: this.sessionOrderPaymentsOrderPaymentComponents
        };
        this.componentsMap = map;
    }

    public async onRequestInvoiceChange(value : any){
        // update invoice flag of current Order
        await this.api.update(this.instance.entity, [this.instance.id], { has_invoice: value });
        await this.load(this.instance.id);
    }

    public async onclickFinish() {
        try {
            await this.api.fetch('?do=lodging_order_do-pay', {id : this.instance.id });
            this.router.navigate(['/session/'+this.instance.session_id.id+'/order/'+this.instance.id+'/ticket']);
        }
        catch(response) {
            console.warn(response);
            this.snack.open('Impossible de finaliser la commande', 'ERROR');
        }
    }

    public async ngOnInit() {
        // fetch the IDs from the route
        this.route.params.subscribe(async (params) => {
            if (params && params.hasOwnProperty('order_id')) {
                try {
                    await this.load(<number>params['order_id']);
                    this.ready = true;
                }
                catch (error) {
                    console.warn(error);
                }
            }
        });
    }


    /**
     * Load an Order object using the sale_pos_order_tree controller
     * @param order_id
     */
    async load(order_id: number) {
        if (order_id > 0) {
            try {
                const data = await this.api.fetch('?get=lodging_sale_pos_order_tree', { id: order_id, variant: 'payments' });
                if(data) {
                    this.update(data);
                }
                if(this.instance.status == 'paid') {
                    this.router.navigate(['/session/'+this.instance.session_id.id+'/order/'+this.instance.id+'/ticket']);
                }
                // fetch order lines (ordered products that haven't been paid yet)
                this.orderLines = await this.api.collect('sale\\pos\\OrderLine', [[['order_id', '=', this.instance.id], ['order_payment_id', '=', 0]],[['order_id', '=', this.instance.id], ['order_payment_id', '=', null]] ], ['funding_id', 'has_funding', 'qty', 'price', 'total', 'order_payment_id'], 'id', 'asc', 0, 100);
                this.dataSource = new MatTableDataSource(this.orderLines);
                this.selection = new SelectionModel(true, []);
                // select all lines by default
                this.selection.select(...this.dataSource.data);

                this.calcAreAllOrderLinesAssigned();
            }
            catch (response) {
                console.log(response);
                throw 'unable to retrieve given order';
            }

            // scroll to the last payment
            setTimeout( () => {
                this.scrollToBottom();
            }, 150);
        }
    }

    /**
     *
     * @param values
     */
    public update(values: any) {
        super.update(values);

        this.orderPayments = values.order_payments_ids;
        this.calcAreAllPaymentsPaid();
    }

    public async ondeletePayment(line_id: number) {
        // a line has been removed: reload tree
        this.load(this.instance.id);
    }

    public async onupdatePayment(line_id: number) {
        // a line has been updated: reload tree
        await this.load(this.instance.id);
    }

    public async onupdateQty() {
        // a line has been removed: reload tree
        this.load(this.instance.id);
    }

    public calcDueRemaining() {
        return Math.max(0, this.instance.price - this.instance.total_paid);
    }

    /**
     * Handler for payment-add button.
     * Adds a new payment only if all payments are paid and there is some due amount left.
     */
    public async onclickAddPayment() {
        // check consistency
        if(!this.canAddPayment()) {
            return;
        }
        try {
            let orderPayment = await this.api.create((new OrderPayment()).entity, { order_id: this.instance.id });
            // create a default part with remaining amount
            await this.api.create((new OrderPaymentPart()).entity, { order_id: this.instance.id, order_payment_id: orderPayment.id });
            // reload tree
            await this.load(this.instance.id);
        }
        catch(response) {
            console.log('unexpected error', response);
        }
    }

    public async onclickAddProduct() {
        // retrieve selected ids
        const order_lines_ids: number[] = this.selection.selected.map( (a:any) => a.id);

        // find a suitable payment to add the lines to
        let orderPayment: any = null;
        for(let payment of this.instance.order_payments_ids) {
            if(payment.status != 'paid') {
                orderPayment = payment;
                break;
            }
        }

        // no suitable payment : create a new one
        if(!orderPayment) {
            orderPayment = await this.api.create((new OrderPayment()).entity, { order_id: this.instance.id });
        }

        if(this.areAllExistingPaymentPartsPaid(orderPayment.id)) {
            await this.createDefaultPaymentPart(orderPayment);
        }

        try {
            // add selected product to the current (latest) payment
            await this.api.update('sale\\pos\\OrderPayment', [orderPayment.id], {order_lines_ids: order_lines_ids});

            // remove added items from product list
            const remainingOrderLines: any[] = this.dataSource.data.filter( (a:any) => (order_lines_ids.indexOf(a.id) < 0) );
            this.dataSource = new MatTableDataSource(remainingOrderLines);

            // reload the Tree
            await this.load(this.instance.id);
        }
        catch(response) {
            console.log('unexpected error', response);
        }
    }

    private async createDefaultPaymentPart(orderPayment: OrderPayment) {
        const amount: number = this.selection.selected.reduce(
            (ac: number, orderLine: OrderLine) => ac + orderLine.price,
            0.0
        );

        const firstPaidPaymentPart = this.getFirstPaidPaymentPart();

        try {
            if(firstPaidPaymentPart && firstPaidPaymentPart.payment_method === 'booking') {
                await this.updatePaymentPartAmount(firstPaidPaymentPart, amount);
            }
            else {
                await this.api.create(
                    (new OrderPaymentPart()).entity,
                    { order_id: this.instance.id, order_payment_id: orderPayment.id, amount }
                );
            }
        }
        catch (response) {
            console.log('unexpected error', response);
        }
    }

    private getFirstPaidPaymentPart(): OrderPaymentPart {
        for(const paymentComponent of this.sessionOrderPaymentsOrderPaymentComponents) {
            for(const paymentPartComponent of paymentComponent.sessionOrderPaymentsPaymentPartComponents) {
                if(paymentPartComponent.instance.status === 'paid') {
                    return paymentPartComponent.instance;
                }
            }
        }

        return null;
    }

    private async updatePaymentPartAmount(paymentPart: OrderPaymentPart, amountToAdd: number) {
        await this.api.update(
            (new OrderPaymentPart()).entity,
            [paymentPart.id],
            {
                amount: paymentPart.amount + amountToAdd
            }
        );
    }

    public onclickPayment(index: number) {
        this.selectedPaymentIndex = index;
    }

    public onclickNext(value: any) {
        // this.current_pane = value;
        let newRoute = this.router.url.replace('payments', 'lines');
        this.router.navigateByUrl(newRoute);
    }

    public async onPadPressed(value: any) {
        let children = this.componentsMap.order_payments_ids.toArray();
        let child = children[this.selectedPaymentIndex];
        let payment = child;

        if (child.display != "products") {

            let paymentPart: SessionOrderPaymentsPaymentPartComponent = payment?.getPaymentPart();

            // retrieve the payment method
            let payment_method = paymentPart?.instance.payment_method;
            let payment_amount = (paymentPart && paymentPart.instance.amount)?paymentPart.instance.amount:0;

            if (this.digits?.toString()?.includes('.') && this.digits[this.digits.length - 1] == ".") {
                this.digits = payment_amount + ".";
            }
            else {
                this.digits = payment_amount
            }

            value = value.toString();
            this.digits = this.digits?.toString();
            if(value == "50" || value == "10" || value == "20") {
                value = parseInt(value);
                this.digits = parseFloat(this.digits);
                this.digits += value;
            }
            else if(value == ",") {
                if (!this.digits?.includes('.')) {
                    this.digits += ".";
                }
            }
            else if(value == 'backspace') {
                this.digits = 0;
                /*
                // we remove two chars (digits + comma) if value is a comma
                let test = this.digits?.slice(0, -1);
                if (test?.slice(-1) == '.') test = test?.slice(0, -1);
                this.digits = test;
                // no digits left: set value to 0
                if (this.digits == "") {
                    this.digits = 0;
                }
                */
            }
            else if(value != '+/-') {
                this.digits += value;
            }

            paymentPart.update({ amount: parseFloat(this.digits), payment_method: payment_method });
        }
    }

    public onclickFullscreen() {
        const elem:any = document.documentElement;
        if(elem.requestFullscreen) {
            elem.requestFullscreen();
        }
        else if(elem.mozRequestFullScreen) {
            elem.mozRequestFullScreen();
        }
        else if(elem.webkitRequestFullscreen) {
            elem.webkitRequestFullscreen();
        }
        else if(elem.msRequestFullscreen) {
            elem.msRequestFullscreen();
        }
    }

    public async onvalidatePayment(index : number) {
        let payment = this.instance.order_payments_ids[index];
        try {
            await this.api.fetch('/?do=sale_pos_payment_validate&id='+payment.id);
            this.load(this.instance.id);
        }
        catch(response) {
            console.log('error updating payment', response);
        }
    }

    public canAddPayment() {
        // check if all payment are marked as paid
        if(this.instance.order_payments_ids.length) {
            // do not allow creation of new payment if there is still one open
            for(let payment of this.instance.order_payments_ids) {
                if(payment.status != 'paid') {
                    return false;
                }
            }
        }
        if(!this.orderLines || !this.orderLines.length) {
            return false;
        }
        return true;
    }

    public canFinish() {
        if(!this.areAllOrderLinesAssigned || !this.areAllPaymentsPaid) {
            return false;
        }

        return (this.ready && this.instance.price <= this.instance.total_paid);
    }

    private calcAreAllOrderLinesAssigned() {
        let allAssigned = true;
        for(const line of this.orderLines) {
            if(!line.order_payment_id) {
                allAssigned = false;
                break;
            }
        }

        this.areAllOrderLinesAssigned = allAssigned;
    }

    private calcAreAllPaymentsPaid() {
        let areAllPaymentsPaid = true;
        for(const orderPayment of this.orderPayments) {
            if(orderPayment.status !== 'paid') {
                areAllPaymentsPaid = false;
                break;
            }
        }

        this.areAllPaymentsPaid = areAllPaymentsPaid;
    }

    private areAllExistingPaymentPartsPaid(orderPaymentId?: number) {
        for(const paymentComponent of this.sessionOrderPaymentsOrderPaymentComponents) {
            if(orderPaymentId && paymentComponent.instance.id !== orderPaymentId) {
                continue;
            }

            for(const paymentPartComponent of paymentComponent.sessionOrderPaymentsPaymentPartComponents) {
                if(paymentPartComponent.instance.status != 'paid') {
                    return false;
                }
            }
        }

        return true;
    }

    public async onchangeCustomer(event: any){
        await this.api.update(this.instance.entity, [this.instance.id], { customer_id: event.id });
        this.load(this.instance.id);
    }

    public isAllSelected() {
        const numSelected = this.selection?.selected.length;
        const numRows = this.dataSource?.data.length;
        return numSelected === numRows;
    }

    private scrollToBottom() {
        const el: HTMLDivElement = this.payments.nativeElement;
        el.scrollTop = Math.max(0, el.scrollHeight - el.offsetHeight);
    }

    /**
     * Selects all rows if they are not all selected; otherwise clear selection.
     *
     */
    public toggleAllRows() {
        if (this.isAllSelected()) {
            this.selection.clear();
            return;
        }

        this.selection.select(...this.dataSource.data);
    }

    public applyFilter(event:any) {
        const filterValue = (event.target as HTMLInputElement).value;
        this.dataSource.filter = filterValue.trim().toLowerCase();
    }

    public onclickProductsList(index: number) {
        this.selectedPaymentIndex = index;
        this.show_products = true;
    }
}
