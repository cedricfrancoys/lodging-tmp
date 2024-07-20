import { Component, Inject, OnInit, OnChanges, NgZone, Output, Input, EventEmitter, SimpleChanges, AfterViewInit, ViewChild } from '@angular/core';
import { AuthService, ApiService, ContextService, TreeComponent } from 'sb-shared-lib';

import { AbstractControl, FormControl, Validators } from '@angular/forms';
import { MatAutocomplete, MatAutocompleteSelectedEvent } from '@angular/material/autocomplete';

import { BookingLineGroup } from '../../../../_models/booking_line_group.model';
import { BookingAgeRangeAssignment } from '../../../../_models/booking_agerange_assignment.model';
import { Booking } from '../../../../_models/booking.model';

import {MatSnackBar} from '@angular/material/snack-bar';

import { debounceTime } from 'rxjs/operators';


interface BookingGroupAgeRangeComponentsMap {
};

@Component({
  selector: 'booking-services-booking-group-agerangeassignment',
  templateUrl: 'agerange.component.html',
  styleUrls: ['agerange.component.scss']
})
export class BookingServicesBookingGroupAgeRangeComponent extends TreeComponent<BookingAgeRangeAssignment, BookingGroupAgeRangeComponentsMap> implements OnInit, OnChanges, AfterViewInit  {
    // server-model relayed by parent
    @Input() set model(values: any) { this.update(values) }
    @Input() agerange: BookingAgeRangeAssignment;
    @Input() group: BookingLineGroup;
    @Input() booking: Booking;
    @Output() updated = new EventEmitter();
    @Output() updating = new EventEmitter();
    @Output() deleted = new EventEmitter();

    public ready: boolean = false;

    public age_range_id: number;
    public qtyFormControl: FormControl;

    constructor(
            private api: ApiService,
            private auth: AuthService,
            private zone: NgZone,
            private snack: MatSnackBar
        ) {
        super( new BookingAgeRangeAssignment() );
        this.qtyFormControl = new FormControl('', [Validators.required, this.validateQty.bind(this)])
    }

    private validateQty(c: AbstractControl) {
        // qty cannot be zero
        return (this.instance
            && this.group
            && c.value > 0) ? null : {
            validateQty: {
                valid: false
            }
        };
    }

    public ngOnChanges(changes: SimpleChanges) {
        if(changes.model) {
        }
    }

    public ngAfterViewInit() {
        // init local componentsMap
        let map:BookingGroupAgeRangeComponentsMap = {
        };
        this.componentsMap = map;

        this.age_range_id = this.instance.age_range_id;
        this.qtyFormControl.setValue(this.instance.qty);
    }


    public ngOnInit() {
        this.ready = true;

        this.qtyFormControl.valueChanges.pipe(debounceTime(500)).subscribe( () => {
            if(this.qtyFormControl.invalid) {
                this.qtyFormControl.markAsTouched();
                return;
            }
        });

    }

    public async update(values:any) {
        super.update(values);
        // assign VM values
        this.qtyFormControl.setValue(this.instance.qty);
    }

    public async onupdateAgeRange(age_range:any) {
        if(this.qtyFormControl.value <= 0) {
            return;
        }
        this.updating.emit(true);
        let prev_age_range_id = this.instance.age_range_id;
        this.instance.age_range_id = age_range.id;
        // notify back-end about the change
        try {
            await this.api.update(this.instance.entity, [this.instance.id], {
                age_range_id: age_range.id,
            });
            // relay change to parent component (update nb_pers)
            this.updated.emit();
        }
        catch(response) {
            this.api.errorFeedback(response);
            // rollback
            this.instance.age_range_id = prev_age_range_id;
        }
        this.updating.emit(false);
    }

    public async onupdateQty() {
        if(this.qtyFormControl.value <= 0) {
            return;
        }
        if(this.instance.age_range_id <= 0) {
            return;
        }
        this.updating.emit(true);
        // notify back-end about the change
        try {
            await this.api.update(this.instance.entity, [this.instance.id], {
                qty: this.qtyFormControl.value
            });
            // relay change to parent component (update nb_pers)
            this.updated.emit();
        }
        catch(response) {
            this.api.errorFeedback(response);

        }
        this.updating.emit(false);
    }


}
