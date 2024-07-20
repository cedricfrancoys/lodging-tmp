import { Component, ElementRef, EventEmitter, Input, OnInit, Output, ViewChild } from '@angular/core';
import { FormControl } from '@angular/forms';
import { MatAutocomplete } from '@angular/material/autocomplete';
import { Observable } from 'rxjs';
import { debounceTime, switchMap, filter } from 'rxjs/operators';
import { ApiService } from 'sb-shared-lib';

@Component({
    selector: 'session-order-payments-select-booking',
    templateUrl: './select-booking.component.html',
    styleUrls: ['./select-booking.component.scss']
})
export class SessionOrderPaymentsSelectBookingComponent implements OnInit {

    @Input() id: number;
    @Input() centerId: number;
    @Input() hint?: string = '';
    @Input() noResult?: string = '';
    @Input() placeholder?: string = '';

    @Output() itemSelected: EventEmitter<object> = new EventEmitter<object>();

    @ViewChild('inputControl') inputControl: ElementRef;
    @ViewChild('inputAutocomplete') inputAutocomplete: MatAutocomplete;

    private selectedBooking?: object = null;

    public inputFormControl = new FormControl();

    public autocompleteBookingList$: Observable<any>;

    constructor(
      private api: ApiService
    ) {}

    public ngOnInit() {
        this.watchInputValueChangesToLoadAutocompleteBookingList();

        if (this.id > 0) {
            this.loadBooking(this.id);
        }
    }

    private watchInputValueChangesToLoadAutocompleteBookingList() {
        this.autocompleteBookingList$ = this.inputFormControl.valueChanges.pipe(
            debounceTime(300),
            filter((value: any) => !this.selectedBooking || this.selectedBooking != value),
            switchMap(async (value: string) => await this.fetchBookingsByNameOrIdentity(value))
        );
    }

    private async loadBooking(id: number) {
        const booking = await this.fetchBooking(id);
        if (booking) {
            this.selectedBooking = booking;
            this.inputFormControl.setValue(this.selectedBooking);
        }
    }

    private async fetchBooking(id: number) {
        const bookings = await this.api.collect(
                'lodging\\sale\\booking\\Booking',
                ['id', '=', id],
                ['id', 'name', 'customer_id.name']
            );
        return bookings[0] ?? null;
    }

    public onFocus() {
        this.triggerAutocompleteRefresh();
    }

    private triggerAutocompleteRefresh() {
        this.inputFormControl.setValue('');
    }

    public onBlur() {
        this.restoreInputValue();
    }

    private restoreInputValue() {
        if (this.selectedBooking) {
            this.inputFormControl.setValue(this.selectedBooking)
        } else {
            this.inputFormControl.setValue(null);
        }
    }

    public onReset() {
        this.inputFormControl.setValue(null);
    }

    public onChange(event: any) {
        if (!event?.option?.value) {
            return;
        }

        this.selectedBooking = event.option.value;
        this.inputFormControl.setValue(this.selectedBooking);
        this.itemSelected.emit(this.selectedBooking);
    }

    private async fetchBookingsByNameOrIdentity(clue: string) {

        return await this.fetchBookings(clue);
    }

    private async fetchBookings(clue: string, limit: number = 25) {
        let bookings = [];

        const domain: any[] = [
            ['status', 'in', ['checkedin', 'checkedout']],
            ['is_cancelled','=', false],
            ['center_id', '=', this.centerId]
        ];

        try {
            bookings = await this.api.get(
                '?get=lodging_booking_pos-search',
                {
                    clue: clue,
                    domain: JSON.stringify(domain),
                    limit: limit
                }
            );
        }
        catch (response) {
            console.warn('unable to retrieve bookings');
        }

        return bookings;
    }

    public bookingDisplay(booking?: any) {
        return booking ? `${booking.name} - ${booking.customer_id.name}` : '';
    }
}
