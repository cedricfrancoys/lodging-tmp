import { Component, OnInit, AfterViewInit, NgZone } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { readyException } from 'jquery';
import { ApiService, ContextService, SbDialogConfirmDialog } from 'sb-shared-lib';
import { MatDialog } from '@angular/material/dialog';
import { CashdeskSession, Order as BaseOrder } from './orders.model';
import { OrderPaymentPart } from '../order/payments/_models/payment-part.model';

class Order extends BaseOrder {
    public paid_editable: boolean;
}

@Component({
  selector: 'session-orders',
  templateUrl: 'orders.component.html',
  styleUrls: ['orders.component.scss']
})
export class SessionOrdersComponent implements OnInit, AfterViewInit {

  public ready: boolean = false;

  public session: CashdeskSession = new CashdeskSession();

  public orders: Order[] = new Array<Order>();

    constructor(
        private router: Router,
        private route: ActivatedRoute,
        private zone: NgZone,
        private api: ApiService,
        private dialog: MatDialog,
        private context: ContextService
    ) {}

    public ngAfterViewInit() {
        console.log('SessionOrdersComponent::ngAfterViewInit');
    }

    public ngOnInit() {
        console.log('SessionOrdersComponent init');
        // fetch the ID from the route
        this.route.params.subscribe( async (params) => {

            if(params && params.hasOwnProperty('session_id')) {
                try {
                    await this.load(<number> params['session_id']);
                    this.ready = true;
                }
                catch(error) {
                    console.warn(error);
                }
            }
        });
    }

    private async load(id: number) {
        if(id > 0) {
            // sync routes on menu pane
            let descriptor:any = {
                context: {
                    entity:  'lodging\\sale\\pos\\CashdeskSession',
                    type:    'form',
                    name:    'default',
                    mode:    'view',
                    purpose: 'view',
                    domain: ['id', '=', id]
                }
            };
            this.context.change(descriptor);
            try {
                const result:any = await this.api.read(CashdeskSession.entity, [id], Object.getOwnPropertyNames(new CashdeskSession()));
                if(result && result.length) {
                    this.session = <CashdeskSession> result[0];
                    try {
                        const result:any = await this.api.collect(Order.entity, [
                                ['session_id', '=', id],
//                                ['status', '<>', 'paid']
                            ],
                            [
                                'has_funding',
                                'customer_id.name',
                                'order_payment_parts_ids.payment_method',
                                ...Object.getOwnPropertyNames(new BaseOrder())
                            ],
                            'created',
                            'desc',
                            0, 500
                        );
                        if(result && result.length) {
                            for(let order of result) {
                                order.paid_editable = this.isPaidAndEditable(order);
                            }

                            this.orders = result;
                        }
                    }
                    catch(response) {
                        console.warn('unable to retrieve orders');
                    }
                }
            }
            catch(response) {
                throw 'unable to retrieve given session';
            }
        }
    }

    private isPaidAndEditable(order: any) {
        if(order.status !== 'paid') {
            return false;
        }

        let paid_editable = false;
        const paidByBooking = order.order_payment_parts_ids.findIndex(
            (part: OrderPaymentPart) => part.payment_method === 'booking'
        ) !== -1;

        if(!order.has_funding && !paidByBooking) {
            const orderModified = new Date(order.modified);
            const orderModifiedDiffDays = Math.floor(
                ((new Date()).getTime() - orderModified.getTime()) / (1000 * 60 * 60 * 24)
            );

            paid_editable = orderModifiedDiffDays <= 7;
        }

        return paid_editable;
    }

    public async onclickNewOrder() {
        // create a new order
        try {
            const order:any = await this.api.create(Order.entity, { session_id: this.session.id });
            // and navigate to it
            this.router.navigate(['/session/'+this.session.id+'/order/'+order.id+'/lines']);
        }
        catch(response) {
            console.log(response);
        }
    }

    public onclickSelectOrder(order:any) {
        if(order.status == 'payment') {
            this.router.navigate(['/session/'+this.session.id+'/order/'+order.id+'/payments']);
        }
        else if(order.status == 'paid') {
            this.router.navigate(['/session/'+this.session.id+'/order/'+order.id+'/ticket']);
        }
        else {
            this.router.navigate(['/session/'+this.session.id+'/order/'+order.id]);
        }
    }

    public async onclickModifyOrder(order: any) {
        try {
            await this.api.fetch('?do=lodging_order_do-unpay', { id : order.id });

            this.onclickSelectOrder({
                ...order,
                status: 'payment'
            });
        }
        catch(response) {
            console.warn(response);
        }
    }

    public async onclickDeleteOrder(order: any) {
        const dialog = this.dialog.open(SbDialogConfirmDialog, {
                width: '33vw',
                data: {
                    title: "Suppression d'une commande",
                    message: "Êtes-vous certain de vouloir supprimer cette commande ? <br />Cette opération est irréversible.",
                    yes: 'Oui',
                    no: 'Non'
                }
            });

        try {
            await new Promise( async(resolve, reject) => {
                dialog.afterClosed().subscribe( async (result) => (result)?resolve(true):reject() );
            });
        }
        catch(error) {
            // user discarded the dialog (selected 'no')
            return;
        }

        // remove order from the list
        let index = this.orders.findIndex( (elem) => (elem.id == order.id) );
        if(index > -1) {
            this.orders.splice(index, 1);
        }
        try {
            await this.api.remove(Order.entity, [order.id], true);
        }
        catch(response) {
            this.api.errorFeedback(response);
            this.load(this.session.id);
        }
    }

    public onclickCloseSession() {
        this.router.navigate(['/session/'+this.session.id+'/close']);
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

    public getOrderStatus(order:Order): string {
        let result: string = '';
        let map: any = {
            'pending': 'en cours',
            'payment': 'paiement',
            'paid': 'terminé'
        };

        if(order.status && map.hasOwnProperty(order.status)) {
            result = map[order.status];
        }
        return result;
    }
}
