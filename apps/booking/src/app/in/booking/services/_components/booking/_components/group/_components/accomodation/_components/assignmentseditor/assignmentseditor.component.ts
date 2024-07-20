import { Component, OnInit, AfterViewInit, Input, Output, EventEmitter, ChangeDetectorRef, ViewChildren, QueryList, Host, OnChanges, SimpleChanges, ViewChild } from '@angular/core';
import { FormControl, Validators } from '@angular/forms';
import { MatDialog, MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { ApiService, ContextService, TreeComponent, SbDialogConfirmDialog } from 'sb-shared-lib';
import { MatSlideToggle } from '@angular/material/slide-toggle';
import { BookingLineGroup } from '../../../../../../_models/booking_line_group.model';
import { BookingAccomodation } from '../../../../../../_models/booking_accomodation.model';
import { BookingAccomodationAssignment } from '../../../../../../_models/booking_accomodation_assignment.model';
import { Booking } from '../../../../../../_models/booking.model';
import { RentalUnitClass } from 'src/app/model/rental.unit.class';
import { Observable, ReplaySubject } from 'rxjs';
import { BookingServicesBookingGroupAccomodationAssignmentsEditorAssignmentComponent } from './_components/assignment.component';

// declaration of the interface for the map associating relational Model fields with their components

@Component({
    selector: 'booking-services-booking-group-accomodation-assignmentseditor',
    templateUrl: 'assignmentseditor.component.html',
    styleUrls: ['assignmentseditor.component.scss']
})
export class BookingServicesBookingGroupAccomodationAssignmentsEditorComponent implements OnInit, OnChanges, AfterViewInit  {

    @Input() group: BookingLineGroup;
    @Input() booking: Booking;
    @Input() accommodation: BookingAccomodation;
    @Input() assignments: BookingAccomodationAssignment[];

    @ViewChildren(BookingServicesBookingGroupAccomodationAssignmentsEditorAssignmentComponent) BookingServicesBookingGroupAccomodationAssignmentComponents: QueryList<BookingServicesBookingGroupAccomodationAssignmentsEditorAssignmentComponent>;


    public ready: boolean = false;

    // local values
    public originalAvailableRentalUnits: RentalUnitClass[] = [];

    public availableRentalUnits: RentalUnitClass[] = [];
    public rentalUnitsAssignments: BookingAccomodationAssignment[] = [];

    public selectedRentalUnits: number[] = [];

    constructor(
        private cd: ChangeDetectorRef,
        private api: ApiService,
        private dialog: MatDialog,
        private context: ContextService
    ) {

    }

    public ngOnChanges(changes: SimpleChanges) {
        if(changes.model) {


        }
    }

    public ngAfterViewInit() {
        // init local componentsMap

        this.loadAvailableRentalUnits();
    }

    public async ngOnInit() {
        this.ready = true;
        // make a copy of received assignments
        this.rentalUnitsAssignments = this.assignments.map(item => Object.assign( <BookingAccomodationAssignment> {}, item));
    }

    public async loadAvailableRentalUnits() {
        // reset rental units listing
        this.availableRentalUnits.splice(0);
        try {
            // retrieve rental units available for assignment
            const data = await this.api.fetch('?get=lodging_booking_rentalunits', {
                booking_line_group_id: this.accommodation.booking_line_group_id,
                product_model_id: this.accommodation.product_model_id.id
            });
            for(let item of data) {
                this.originalAvailableRentalUnits.push(<RentalUnitClass> item);
                this.availableRentalUnits.push(<RentalUnitClass> item);
            }
        }
        catch(response) {
            this.api.errorFeedback(response);
        }
    }

    public async ondeleteAssignment(assignment_id: any) {
        // add related rental unit back to left pane
        let assignment = this.rentalUnitsAssignments.find( (e:any) => e.id == assignment_id );
        if(assignment) {
            // retrieve original rental unit, if loaded
            let originalRentalUnit = this.originalAvailableRentalUnits.find( (e:any) => e.id == assignment.rental_unit_id.id );

            // remove rental unit from left pane, if present
            let index_available_rental_unit = this.availableRentalUnits.findIndex( (e:any) => e.id == assignment.rental_unit_id.id );
            if(index_available_rental_unit > -1) {
                this.availableRentalUnits.splice(index_available_rental_unit, 1);
            }

            // #memo - values in the left pane are impacted by the SPM itself

            let capacity = assignment.rental_unit_id.capacity;
            if(originalRentalUnit != undefined && originalRentalUnit.capacity > capacity) {
                capacity = originalRentalUnit.capacity;
            }

            let rentalUnit = new RentalUnitClass(
                    assignment.rental_unit_id.id,
                    assignment.rental_unit_id.name,
                    capacity,
                    0,
                    assignment.rental_unit_id.is_accomodation
                );

            this.availableRentalUnits.push(rentalUnit);
        }

        // remove assignment from right pane
        this.rentalUnitsAssignments.splice(this.rentalUnitsAssignments.findIndex( (e:any) => e.id == assignment_id ), 1);
    }

    public async onupdateAssignment(assignment:any) {
        console.log("AssignmentEditor::onupdateAssignment", assignment);
        let index = this.rentalUnitsAssignments.findIndex( (e:any) => e.id == assignment.id );
        if(index >= 0) {
            this.rentalUnitsAssignments[index].qty = assignment.qty;
        }
    }

    public leftSelectRentalUnit(checked: boolean, rental_unit_id: number) {
        let index = this.selectedRentalUnits.indexOf(rental_unit_id);
        if(index == -1) {
            this.selectedRentalUnits.push(rental_unit_id);
        }
        else if(!checked) {
            this.selectedRentalUnits.splice(index, 1);
        }
    }

    public addSelection() {
        console.log('adding selection');
        // add assignments to the right pane
        for(let rental_unit_id of this.selectedRentalUnits) {
            // retrieve selected rentalUnit
            const rentalUnit = <RentalUnitClass> this.availableRentalUnits.find( (item) => item.id == rental_unit_id );
            if(!rentalUnit) {
                continue;
            }

            let calc_capacity = rentalUnit.capacity;
            // compare with capacity of Product Model from SPM
            if(this.accommodation.product_model_id.qty_accounting_method == 'accomodation' && this.accommodation.product_model_id.capacity && this.accommodation.product_model_id.capacity < calc_capacity) {
                calc_capacity = this.accommodation.product_model_id.capacity;
            }
            let assignment_qty = this.group.nb_pers;
            if(assignment_qty > calc_capacity) {
                assignment_qty = calc_capacity;
            }
            let assignment = new BookingAccomodationAssignment(0, rentalUnit, assignment_qty, this.group.id)
            this.rentalUnitsAssignments.push(assignment);
        }

        // remove rental unit from left pane
        for(let rental_unit_id of this.selectedRentalUnits) {
            this.availableRentalUnits.splice(this.availableRentalUnits.findIndex( (e:any) => e.id == rental_unit_id ), 1);
        }

        // empty selection
        this.selectedRentalUnits.splice(0);
    }

    public addAll() {
        // unselect selected items
        this.selectedRentalUnits.splice(0);
        // select all
        for(let rentalUnit of this.availableRentalUnits) {
            this.selectedRentalUnits.push(rentalUnit.id);
        }
        this.addSelection();
    }

}
