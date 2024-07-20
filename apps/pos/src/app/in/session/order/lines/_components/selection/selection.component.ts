import { Component, EventEmitter, Input, OnChanges, OnInit, Output, SimpleChanges, ViewChild } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { ApiService, AuthService } from 'sb-shared-lib';
import { Order } from '../../_models/order.model';

import { MatTableDataSource } from '@angular/material/table';
import { MatPaginator, MatPaginatorModule } from '@angular/material/paginator';
import { MatTabChangeEvent } from '@angular/material/tabs';

@Component({
  selector: 'session-order-lines-selection',
  templateUrl: './selection.component.html',
  styleUrls: ['./selection.component.scss']
})
export class SessionOrderLinesSelectionComponent implements OnInit {

    @Output() addedFunding = new EventEmitter();
    @Output() addedProduct = new EventEmitter();

    @Input() order: Order;

    public ready: boolean = false;

    public bookings: any;
    public fundings: any;
    public products : any;


    public funding: boolean = false;

    public productsDataSource: MatTableDataSource<any>;
    public bookingsDataSource: MatTableDataSource<any>;
    public fundingsDataSource: MatTableDataSource<any>;


    public selected_product_id : number;
    public selected_funding_id : number;

    private selected_tab_index: number = 0;

    constructor(
        private api: ApiService,
        private route: ActivatedRoute,
        private auth: AuthService
    ) {
        this.productsDataSource = new MatTableDataSource();
        this.bookingsDataSource = new MatTableDataSource();
        this.fundingsDataSource = new MatTableDataSource();
    }

    async ngOnInit() {
        // init lists
        this.update();
    }

    public update() {
        // load products tab pane content
        this.loadProducts();
        // load bookings
        this.loadBookings();
    }

    /**
     * Handler for tab selection change.
     * Feeds the datasource according to the newly selected tab.
     *
     * @param event
     */
    public async onSelectedTab(event: MatTabChangeEvent) {
        this.selected_tab_index = event.index;

        // reset the level
        this.funding = false;
    }

    public async createOrderLine(elem: any, type: string) {
        if(type == "product"){
            this.addedProduct.emit(elem);
        }
        else if(type == "funding"){
            this.addedFunding.emit(elem);
        }
    }

    /**
     * Loads the products using dedicated controller to feed only with products having a price for current center.
     *
     * @param filter
     */
    private async loadProducts(filter: string = '') {
        try {
            this.products = await this.api.fetch('?get=lodging_sale_catalog_product_pos-collect', { center_id: this.order.session_id.center_id.id, filter: filter });
            this.productsDataSource = new MatTableDataSource(this.products);
        }
        catch(response) {
            console.log('error while retrieving products.', response);
        }
    }

    private async loadBookings(filter: string = '') {
        const domain = [
            ['name', 'ilike', `%${filter}%`],
            // #todo - to remove
            ['is_cancelled', '=', false],
            ['status', 'not in', ['quote', 'credit_balance', 'balanced']],
            ['center_id', '=', this.order.session_id.center_id.id]
        ];

        const isNotDefaultPosCustomer =
            this.order.customer_id.id != this.order.session_id.center_id.pos_default_customer_id;

        if(isNotDefaultPosCustomer) {
            domain.push(['customer_id', '=', this.order.customer_id.id])
        }

        const fields = ['customer_id.name', 'center_id', 'total', 'date_from', 'date_to', 'price'];

        try {
            this.bookings = await this.api.get(
                '?get=lodging_booking_collect-unpaid',
                {
                    domain: JSON.stringify(domain),
                    fields: JSON.stringify(fields)
                }
            );

            this.bookingsDataSource = new MatTableDataSource(this.bookings);
        }
        catch(response) {
            console.log('error while retrieving unpaid bookings.', response);
        }
    }

    private async loadFundings(booking_id: number) {
        try {
            const bookingFundings = await this.api.collect(
                'lodging\\sale\\booking\\Funding',
                [
                    ['booking_id', '=', booking_id],
                    ['is_paid', '=', false]
                ],
                ['name', 'description', 'due_amount', 'is_paid', 'paid_amount', 'due_date', 'booking_id.customer_id']
            );

            this.fundings = bookingFundings.filter(
                (funding: { is_paid: boolean }) => !funding.is_paid
            );

            this.fundingsDataSource = new MatTableDataSource(this.fundings);
        }
        catch(response) {
            console.log('error while retrieving fundings.', response);
        }
    }

    public async createProductOrderLine(elem: any) {
        this.addedProduct.emit(elem);
    }

    public async applyFilter(event: any = {}) {
        console.log('OrderItemsComponent::applyFilter', event);
        let filter = '';

        if(event.target) {
            filter = (<HTMLInputElement> event.target).value.trim().toLowerCase();
        }

        if(this.selected_tab_index == 0) {
            this.loadProducts(filter);
        }
        else if(this.selected_tab_index == 1) {
            this.loadBookings(filter);
        }
    }

    public selectProduct(row: any) {
        this.createOrderLine(row, 'product');
    }

    public async selectBooking(booking: { id: number }) {
        // show level 2 of booking pane
        this.funding = true;
        // load related fundings
        this.loadFundings(booking.id);
    }

    public selectFunding(row: any) {
        this.createOrderLine(row, 'funding');
    }

}
