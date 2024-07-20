import { Component, ElementRef, HostListener, Inject, OnInit, ViewChild } from '@angular/core';
import { FormControl, NgModel, Validators } from '@angular/forms';
import { MatDialog, MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { ApiService } from 'sb-shared-lib';


class Price {
    constructor(
        public id: number = 0,
        public name: string = '',
        public price: number = 0,
        public vat_rate: number = 0
    ) {}
}

@Component({
    selector: 'line-price-dialog',
    templateUrl: './price.component.html',
    styleUrls: ['./price.component.scss']
})
export class BookingServicesBookingGroupLinePriceDialogComponent {

    public priceInstance: Price;

    public vat_rate: number = 0;
    public unit_price: number = 0;
    public price: number = 0;

    @ViewChild('vat_rate_input') vatRateInput: NgModel;

    @HostListener('window:keyup.Enter', ['$event'])
    onDialogClick(event: KeyboardEvent): void {
        this.onSave();
    }

    constructor(
        public dialogRef: MatDialogRef<BookingServicesBookingGroupLinePriceDialogComponent>,
        @Inject(MAT_DIALOG_DATA) public data: any,
        private api: ApiService
    ) {
        this.vat_rate = this.data.line.vat_rate;
        this.unit_price = this.data.line.unit_price;
        this.price = parseFloat((this.unit_price * (1 + this.vat_rate)).toFixed(2));
    }

    public ngAfterContentInit() {
        this.load( Object.getOwnPropertyNames(new Price()) );
    }

    private async load(fields: any[]) {
        const result = <Array<any>> await this.api.read("sale\\price\\Price", [this.data.line.price_id], fields);
        if(result && result.length) {
            const price = <Price> result[0];
            this.priceInstance = new Price(
                price.id,
                price.name,
                price.price,
                price.vat_rate
            );
        }
    }

    public onClose(): void {
        this.dialogRef.close();
    }

    public onSave(): void {
        if(this.vatRateInput.invalid) {
            console.warn('invalid vat rate');
            return;
        }
        this.dialogRef.close(this);
    }

    public onchangePrice(price:number) {
        this.price = price;
        this.unit_price = parseFloat((this.price / (1 + this.vat_rate)).toFixed(4));
    }

    public onchangeUnitPrice(unit_price: number) {
        this.unit_price = unit_price;
        this.price = parseFloat((this.unit_price * (1 + this.vat_rate)).toFixed(2));
    }

    public onchangeVatRate(vat_rate:number) {
        this.vat_rate = vat_rate;
        this.unit_price = parseFloat((this.price / (1 + this.vat_rate)).toFixed(4));
    }
}