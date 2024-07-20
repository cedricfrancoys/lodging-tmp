import { Component, AfterContentInit, OnInit, NgZone } from '@angular/core';
import { ActivatedRoute, Router, RouterEvent, NavigationEnd } from '@angular/router';
import { MatDialog, MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';

import { ApiService, EnvService, AuthService, ContextService, SbDialogConfirmDialog } from 'sb-shared-lib';
import { MatSnackBar } from '@angular/material/snack-bar';
import { FormControl, Validators } from '@angular/forms';
import { EditorChangeContent, EditorChangeSelection } from 'ngx-quill';


class Booking {
  constructor(
    public id: number = 0,
    public name: string = '',
    public date_from: Date = new Date(),
    public date_to: Date  = new Date(),
    public customer_id: number = 0,
    public customer_identity_id: number = 0,
    public has_payer_organisation: boolean = false,
    public payer_organisation_id: number = 0,
    public center_id: number = 0,
    public contacts_ids: number[] = []
  ) {}
}

class Funding {
  constructor(
    public id: number = 0,
    public name: string = '',
    public type: string = 'installment',
    public due_amount: number  = 0,
    public is_paid: boolean = false,
  ) {}
}

class Partner {
  constructor(
    public id: number = 0,
    public name: string = '',
    public email: string = ''
  ) {}
}


@Component({
  selector: 'booking-funding-invoice',
  templateUrl: 'invoice.component.html',
  styleUrls: ['invoice.component.scss']
})
export class BookingFundingInvoiceComponent implements OnInit, AfterContentInit {

    public loading = true;
    public is_converted = false;

    public booking_id: number;
    public funding_id: number;

    public booking: any = new Booking();
    public funding: any = new Funding();
    public customer: any = new Partner();
    public payer: any = new Partner();

    public payment_terms: any = {
        "id": 1,
        "name": "default"
    };

    public bookingControl:FormControl;
    public fundingControl:FormControl;
    public hasPayerControl: FormControl;


    constructor(
        private dialog: MatDialog,
        private api: ApiService,
        private route: ActivatedRoute,
        private context:ContextService,
        private snack: MatSnackBar,
    ) {

      this.bookingControl = new FormControl();
      this.fundingControl = new FormControl();
      this.hasPayerControl = new FormControl();

  }

  /**
   * Set up callbacks when component DOM is ready.
   */
  public ngAfterContentInit() {
    this.loading = false;
  }



  ngOnInit() {

    // fetch the booking ID from the route
    this.route.params.subscribe( async (params) => {
      if(params) {
          try {
            if(params.hasOwnProperty('booking_id')) {
              this.booking_id = <number> parseInt(params['booking_id']);
              await this.loadBooking();
              await this.loadCustomer();
            }
            if(params.hasOwnProperty('funding_id')) {
              this.funding_id = <number> parseInt(params['funding_id']);
              await this.loadFunding();
            }
          }
          catch(error) {
            console.warn(error);
          }
      }
    });


    /* sync View and Model */

    this.bookingControl.valueChanges.subscribe( (value:number)  => {
      this.booking.name = value;
    });



  }


  private async loadBooking() {
    const result:Array<any> = <Array<any>> await this.api.read("lodging\\sale\\booking\\Booking", [this.booking_id], Object.getOwnPropertyNames(new Booking()));
    if(result && result.length) {
      const item:any = result[0];
      let booking:any = new Booking();
      for(let field of Object.getOwnPropertyNames(booking) ) {
        if(item.hasOwnProperty(field)) {
          booking[field] = item[field];
        }
      }
      this.booking = <Booking> booking;
      this.bookingControl.setValue(booking.name);
      if(this.booking.has_payer_organisation) {
        this.hasPayerControl.setValue(this.booking.has_payer_organisation);
        await this.loadPayer();
      }
    }
  }

  private async loadFunding() {
    const result:Array<any> = <Array<any>> await this.api.read("lodging\\sale\\booking\\Funding", [this.funding_id], Object.getOwnPropertyNames(new Funding()));
    if(result && result.length) {
      const item:any = result[0];
      let funding:any = new Funding();
      for(let field of Object.getOwnPropertyNames(funding) ) {
        if(item.hasOwnProperty(field)) {
          funding[field] = item[field];
        }
      }
      this.funding = <Funding> funding;
      this.fundingControl.setValue(funding.name);
    }
  }

  private async loadCustomer() {
    const result:Array<any> = <Array<any>> await this.api.read("identity\\Partner", [this.booking.customer_id], Object.getOwnPropertyNames(new Partner()));
    if(result && result.length) {
      const item:any = result[0];
      let customer:any = new Partner();
      for(let field of Object.getOwnPropertyNames(customer) ) {
        if(item.hasOwnProperty(field)) {
          customer[field] = item[field];
        }
      }
      this.customer = <Partner> customer;
    }
  }

    private async loadPayer() {
        const result:Array<any> = <Array<any>> await this.api.read("identity\\Partner", [this.booking.payer_organisation_id], Object.getOwnPropertyNames(new Partner()));
        if(result && result.length) {
            const item:any = result[0];
            let payer:any = new Partner();
            for(let field of Object.getOwnPropertyNames(payer) ) {
                if(item.hasOwnProperty(field)) {
                    payer[field] = item[field];
                }
            }
            this.payer = <Partner> payer;
        }
    }

    public displayFundingType() {
        switch(this.funding.type) {
            case 'installment': return 'acompte';
            case 'invoice': return 'facture';
            default: return '';
        }
    }

    public selectBooking(event:any) {
        console.log('booking selected', event);
    }

    public selectFunding(event:any) {
        console.log('funding selected', event);
    }

    public selectPayer(event:any) {
        console.log('payer selected', event);
        this.payer = event;
    }

    public selectPayementTerms(event:any) {
        this.payment_terms = event;
    }

    public async onSubmit() {
        /*
          Validate values (otherwise mark fields as invalid)
        */

        let is_error = false;

        if(!this.payment_terms.id || this.payment_terms.id <= 0) {
            is_error = true;
        }

        if(is_error) return;
        let partner_id = this.customer.id;
        let partner_name = this.customer.name;

        if(this.hasPayerControl.value) {
            partner_id = this.payer.id;
            partner_name = this.payer.name;
        }

        const dialog = this.dialog.open(SbDialogConfirmDialog, {
            width: '33vw',
            data: {
                title: "Création d'une facture",
                message: 'Cette action générera une nouvelle facture, qui sera émise au nom de <br/ ><strong>'+partner_name+'</strong>.<br /><br />Confirmer cette opération ?',
                yes: 'Oui',
                no: 'Non'
            }
        });


        try {
            await new Promise( async(resolve, reject) => {
                dialog.afterClosed().subscribe( async (result) => (result)?resolve(true):reject() );
            });

            this.loading = true;
            this.is_converted = true;

            try {
                await this.api.fetch('/?do=lodging_funding_convert', {
                    id: this.funding.id,
                    partner_id: partner_id,
                    payment_terms_id: this.payment_terms.id
                });
            }
            catch(error) {
                // something went wrong while saving
            }
            this.loading = false;
        }
        catch(error) {
            // user discarded the dialog (selected 'no')
            return;
        }

    }
}