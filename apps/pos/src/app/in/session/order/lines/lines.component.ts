import { Component, OnInit, AfterViewInit, ViewChildren, QueryList, ViewChild } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { ApiService, ContextService, TreeComponent, RootTreeComponent, SbDialogNotifyDialog } from 'sb-shared-lib';
import { CashdeskSession } from '../../_models/session.model';
import { Order } from './_models/order.model';
import { OrderLine } from './_models/order-line.model';
import { SessionOrderLinesOrderLineComponent } from './_components/order-line/order-line.component';
import { SessionOrderLinesSelectionComponent } from './_components/selection/selection.component';
import { MatDialog } from '@angular/material/dialog';
import { AppKeypadLinesComponent } from 'src/app/in/_components/keypad-lines/keypad-lines.component';


// declaration of the interface for the map associating relational Model fields with their components
interface OrderComponentsMap {
    order_lines_ids: QueryList<SessionOrderLinesOrderLineComponent>
};

@Component({
    selector: 'session-order-lines',
    templateUrl: 'lines.component.html',
    styleUrls: ['lines.component.scss']
})
export class SessionOrderLinesComponent extends TreeComponent<Order, OrderComponentsMap> implements RootTreeComponent, OnInit, AfterViewInit {

    @ViewChildren(SessionOrderLinesOrderLineComponent) sessionOrderLinesOrderLineComponents: QueryList<SessionOrderLinesOrderLineComponent>;
    @ViewChild('selection') selection: SessionOrderLinesSelectionComponent;
    @ViewChild('keypad') keypad: AppKeypadLinesComponent;

    public ready: boolean = false;

    public get taxes () {
        return Math.round( (this.instance.price - this.instance.total) * 100) / 100;
    }

    public selected_field: string = 'qty';

    // reference to the selected line component
    private selectedLineComponent: SessionOrderLinesOrderLineComponent;
    // local copy of selected line
    public selectedLine: OrderLine;

    private previousSelectedLineId: number = null;

    // pane to be displayed : 'main', 'discount'
    public current_pane: string = "main";

    private keypad_str:string = '0';
    private last_stroke: number = Date.now();

    constructor(
        private router: Router,
        private route: ActivatedRoute,
        private api: ApiService,
        private dialog: MatDialog,
        private context: ContextService
    ) {
        super(new Order());
    }

    public ngAfterViewInit() {
        // init local componentsMap
        let map: OrderComponentsMap = {
            order_lines_ids: this.sessionOrderLinesOrderLineComponents
        };
        this.componentsMap = map;
    }

    public ngOnInit() {
        // fetch the ID from the route
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
                const data = await this.api.fetch('?get=lodging_sale_pos_order_tree', { id: order_id, variant: 'lines' });
                if (data) {
                    this.update(data);
                }
            }
            catch (response) {
                console.log(response);
                throw 'unable to retrieve given order';
            }
        }
    }


    /**
     *
     * @param values
     */
    public update(values: any) {
        super.update(values);
    }

    public async onupdateLine() {
        // a line has been updated: reload tree
        await this.load(this.instance.id);
    }

    public async ondeleteLine() {
        // a line has been removed: reload tree
        this.load(this.instance.id);
    }

    public async onclickCreateNewLine() {
        await this.api.create((new OrderLine()).entity, { order_id: this.instance.id });
        // reload tree
        this.load(this.instance.id);
    }

    public onclickFullscreen() {
        const elem:any = document.documentElement;
        if (elem.requestFullscreen) {
            elem.requestFullscreen();
        }
        else if (elem.mozRequestFullScreen) {
            elem.mozRequestFullScreen();
        }
        else if (elem.webkitRequestFullscreen) {
            elem.webkitRequestFullscreen();
        }
        else if (elem.msRequestFullscreen) {
            elem.msRequestFullscreen();
        }
    }

    public onSelectLine(line: any) {
        let index = this.sessionOrderLinesOrderLineComponents.toArray().findIndex( (l:any) => l.instance.id == line.id );
        this.selectedLineComponent = this.sessionOrderLinesOrderLineComponents.toArray()[index];
        // create a clone of the selected line
        this.selectedLine = <OrderLine> {...this.selectedLineComponent.instance};
        // #memo - it is more convenient for user to manually switch between modes
        // this.keypad.reset();
    }


    /**
     * Handle keypress event from the keypad.
     * Special keys : 0-9, '%', 'backspace', '+/-', '+', '-'
     */
    public async onPadPressed(key: any) {

        // make sure a line is currently selected
        if(!this.selectedLine) {
            return;
        }

        // special key: '%' is a request for switch to discount pane
        if (key == "%") {
            this.switchPane('discount');
            // force a view refresh
            // #todo - to improve
            this.selectedLine = <OrderLine> { ...this.selectedLine };
            return;
        }

        let now: number = Date.now();

        // after 1.5 sec, the stroke replaces the value; otherwise, the key is append to the value
        if(now - this.last_stroke > 1500 || this.selectedLine.id !== this.previousSelectedLineId) {
            this.keypad_str = '0';
        }

        this.last_stroke = now;

        // adapt string based on received key
        if (key == ",") {
            if(!this.keypad_str.includes('.')) {
                this.keypad_str += ".00001";
            }
            return;
        }
        else if (['+', '-'].indexOf(key) >= 0) {
            if (key == "-") {
                if (this.keypad_str.includes('-')) {
                    this.keypad_str = this.keypad_str.replace('-', '');
                }
                else {
                    this.keypad_str = '-' + this.keypad_str;
                }
            }
            else if (key == "+" && this.keypad_str.includes('-')) {
                this.keypad_str = this.keypad_str.replace('-', '');
            }
        }
        else if (key == 'backspace') {
            if (this.keypad_str.length) {
                // remove last char(s)

                if(this.keypad_str.includes('.')) {
                    let dec_part: string = this.keypad_str.split(".")[1];
                    if(dec_part.length > 2) {
                        this.keypad_str = this.keypad_str.slice(0, -2);
                        let last = this.keypad_str.slice(-1);
                        if(last == '0') {
                            this.keypad_str = this.keypad_str.slice(0, -1);
                        }
                    }
                    else {
                        this.keypad_str = this.keypad_str.slice(0, -1);
                    }
                }
                else {
                    this.keypad_str = this.keypad_str.slice(0, -1);
                }

                if (!this.keypad_str.length) {
                    this.keypad_str = "0";
                }
                // remove decimal separator if unnecessary
                else if(this.keypad_str.includes('.')) {
                    let num_val = parseFloat(this.keypad_str);
                    if(Number.isInteger(num_val)) {
                        this.keypad_str = num_val.toString();
                    }
                }
            }
        }
        else if (/^[0-9]{1}/.test(key)) {
            console.log(this.keypad_str);
            if(this.keypad_str.includes('.')) {
                console.log('point detected', this.keypad_str);
                let value: number = parseFloat(this.keypad_str);
                let int_part: string = '';
                let dec_part: string = '';

                let parts: string[] = this.keypad_str.split(".");
                if(parts.length > 0) {
                    int_part = parts[0];
                }
                if(parts.length > 1) {
                    dec_part = parts[1];
                    // remove trailing 1, if any
                    dec_part = dec_part.substring(0, 4);
                    // remove trailing zeros
                    dec_part = dec_part.replace(/0+$/, '');
                }

                let new_part: string = '';
                if(dec_part == '0' || dec_part == '') {
                    new_part = '' + key;
                }
                else {
                    new_part = '' + dec_part + key;
                }
                this.keypad_str = int_part + '.' + (new_part).padEnd(4, '0') + '1';
            }
            else {
                if(this.keypad_str == "0") {
                    this.keypad_str = '' + key;
                }
                else {
                    this.keypad_str += '' + key;
                }
            }
        }

        // update local copy
        switch (this.selected_field) {
            case 'qty':
                this.selectedLine = <OrderLine> {...this.selectedLine, qty: parseInt(this.keypad_str, 10)};
                break;
            case 'free_qty':
                this.selectedLine = <OrderLine> {...this.selectedLine, free_qty: parseInt(this.keypad_str, 10)};
                break;
            case 'unit_price':
                let price: number = parseFloat(this.keypad_str);
                let unit_price = parseFloat((price / (1 + this.selectedLine.vat_rate)).toFixed(4));
                this.selectedLine = <OrderLine> {...this.selectedLine, unit_price: unit_price};
                break;
            case 'discount':
                this.selectedLine = <OrderLine> {...this.selectedLine, discount: parseFloat(this.keypad_str) / 100};
                break;
            case 'vat_rate':
                this.selectedLine = <OrderLine> {...this.selectedLine, vat_rate: parseFloat(this.keypad_str) / 100};
                break;
        }

        // synchronously refresh selected line Component
        this.selectedLineComponent.update(this.selectedLine);
        // trigger a relay to server
        this.selectedLineComponent.onchange();
        // update previously selected line
        this.previousSelectedLineId = this.selectedLine.id;
    }

    public async onRequestInvoiceChange(value: any) {
        // update invoice flag of current Order
        await this.api.update(this.instance.entity, [this.instance.id], { has_invoice: value });
        await this.load(this.instance.id);
    }

    public async onclickNext(status: string) {
        const orderData: Partial<Order> = { status };

        if(!this.instance.order_lines_ids.length) {
            this.alertNoOrderLinesSelected();
            return;
        }
        else if(this.instance.order_lines_ids.length === 1 && this.instance.order_lines_ids[0].has_funding) {
            const fundingCustomerId = await this.getFundingCustomerId(this.instance.order_lines_ids[0].funding_id);
            if(fundingCustomerId && fundingCustomerId !== this.instance.customer_id.id) {
                orderData.customer_id = fundingCustomerId;
            }
        }

        // update order status
        try {
            await this.api.update(this.instance.entity, [this.instance.id], orderData);
            // this.current_pane = value;
            let newRoute = this.router.url.replace('lines', 'payments');
            this.router.navigateByUrl(newRoute);
        }
        catch(response) {
            // unexpected error
            console.log(response);
        }
    }

    private alertNoOrderLinesSelected() {
        this.dialog.open(SbDialogNotifyDialog, {
            width: '33vw',
            data: {
                title: 'Opération impossible',
                message: 'Sélectionnez des produits ou un financement pour passer au paiement de la commande.',
                ok: 'Fermer'
            }
        });
    }

    private async getFundingCustomerId(id: number): Promise<number> {
        let fundings = [];

        try {
            fundings = await this.api.read(
                'lodging\\sale\\booking\\Funding',
                [id],
                ['booking_id.customer_id']
            );
        }
        catch(response) {
            // unexpected error
            console.log(response);
        }

        return fundings.length ? fundings[0].booking_id.customer_id : null;
    }

    public switchPane(event: string) {
        this.current_pane = event;
    }


    /**
     * Possible values are : qty, free_qty, unit_price, discount, vat_rate
     * @param event
     */
    public onSelectField(event: any) {
        this.selected_field = event;
        this.last_stroke = 0;
    }

    /**
     * Handler of request for adding a funding to the order
     * @param funding
     */
    public async onAddFunding(funding: any) {
        let has_error: boolean = false;

        // make sure the funding hasn't been added already
        this.instance.order_lines_ids.forEach((element: any) => {
            if (element.funding_id == funding.id) {
                has_error = true;
                // display an error message : a funding must be alone on an order
                const dialog = this.dialog.open(SbDialogNotifyDialog, {
                    width: '33vw',
                    data: {
                        title: "Opération impossible",
                        message: "Un même financement ne peut pas être placé plusieurs fois sur une commande.",
                        ok: 'Fermer'
                    }
                });
            }
        });

        if (!has_error && this.instance.order_lines_ids.length > 0) {
            has_error = true;
            // display an error message : a funding must be alone on an order
            const dialog = this.dialog.open(SbDialogNotifyDialog, {
                width: '33vw',
                data: {
                    title: "Opération impossible",
                    message: "Les commandes doivent comporter soit exclusivement des produits, soit un seul financement.\nSi vous souhaitez ajouter ce financement, retirez d'abord les produits déjà présents.",
                    ok: 'Fermer'
                }
            });
        }

        if(has_error) {
            return;
        }

        try {
            // create a new line
            const line = await this.api.create((new OrderLine()).entity, { order_id: this.instance.id, unit_price: (funding.due_amount-funding.paid_amount), qty: 1, has_funding: true, funding_id: funding.id, name: funding.name });
            // add line to current order
            await this.api.update(this.instance.entity, [this.instance.id], { customer_id: funding.booking_id.customer_id, order_lines_ids: [line.id] });
            await this.load(this.instance.id);
            // this.onSelectLine(line);
        }
        catch(response) {
            // unexpected error
        }
    }

    /**
     * Handler of request for adding a funding to the order
     * @param product
     */
    public async onAddProduct(product: any) {

        let has_error: boolean = false;

        this.instance.order_lines_ids.forEach((element: any) => {
            if (element.has_funding == true) {
                has_error = true;
                const dialog = this.dialog.open(SbDialogNotifyDialog, {
                    width: '33vw',
                    data: {
                        title: "Opération impossible",
                        message: "Les commandes ne peuvent comporter à la fois des produits et des financements. Si vous souhaitez ajouter ce produit, retirez d'abord le financement.",
                        ok: 'Fermer'
                    }
                });

            }
        });

        if(has_error) {
            return;
        }
        try {
            console.log('create order line', product);
            const line = await this.api.create((new OrderLine()).entity, {
                order_id: this.instance.id,
                // price yet is resolved by back-end
                unit_price: 0,
                qty: 1,
                name: product.sku,
                product_id: product.id
            });
            await this.api.update(this.instance.entity, [this.instance.id], { order_lines_ids: [line.id] });
            await this.load(this.instance.id);
            // select line upon next digest
            setTimeout( () => {
                this.onSelectLine(line);
            });
        }
        catch(response) {
            // unexpected error
        }

    }


    public async onchangeCustomer(event : any){
        await this.api.update(this.instance.entity, [this.instance.id], { customer_id: event.id });
        await this.load(this.instance.id);
        // update item selection component (we do it manually because since this.instance is not replaced, ngOnchange won't be triggered)
        this.selection.update();
    }
}
