import { Component, OnInit, AfterViewInit, Input, Output, EventEmitter, ChangeDetectorRef, ViewChildren, QueryList, Host, OnChanges, SimpleChanges, ViewChild } from '@angular/core';
import { FormControl, Validators } from '@angular/forms';
import { MatDialog, MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { ApiService, ContextService, TreeComponent, SbDialogConfirmDialog } from 'sb-shared-lib';
import { MatSlideToggle } from '@angular/material/slide-toggle';
import { BookingLineGroup } from '../../../../_models/booking_line_group.model';
import { BookingAccomodation } from '../../../../_models/booking_accomodation.model';
import { Booking } from '../../../../_models/booking.model';
import { RentalUnitClass } from 'src/app/model/rental.unit.class';
import { Observable, ReplaySubject } from 'rxjs';
import { BookingServicesBookingGroupAccomodationAssignmentComponent } from './_components/assignment.component';
import { BookingServicesBookingGroupAccomodationAssignmentsEditorComponent } from './_components/assignmentseditor/assignmentseditor.component';

// declaration of the interface for the map associating relational Model fields with their components
interface BookingLineAccomodationComponentsMap {
    rental_unit_assignments_ids: QueryList<BookingServicesBookingGroupAccomodationAssignmentComponent>
};

@Component({
    selector: 'booking-services-booking-group-rentalunitassignment',
    templateUrl: 'accomodation.component.html',
    styleUrls: ['accomodation.component.scss']
})
export class BookingServicesBookingGroupAccomodationComponent extends TreeComponent<BookingAccomodation, BookingLineAccomodationComponentsMap> implements OnInit, OnChanges, AfterViewInit  {
    // server-model relayed by parent
    @Input() set model(values: any) { this.update(values) }
    @Input() group: BookingLineGroup;
    @Input() booking: Booking;
    @Input() mode: string = 'view';

    @Output() updated = new EventEmitter();
    @Output() deleted = new EventEmitter();

    @ViewChildren(BookingServicesBookingGroupAccomodationAssignmentComponent) BookingServicesBookingGroupAccomodationAssignmentComponents: QueryList<BookingServicesBookingGroupAccomodationAssignmentComponent>;
    @ViewChild('assignmentsEditor') assignmentsEditor: BookingServicesBookingGroupAccomodationAssignmentsEditorComponent;

    public ready: boolean = false;
    public assignments_editor_enabled: boolean = false;
    public rentalunits: RentalUnitClass[] = [];

    public selectedRentalUnits: number[] = [];
    public action_in_progress: boolean = false;

    constructor(
        private cd: ChangeDetectorRef,
        private api: ApiService,
        private dialog: MatDialog,
        private context: ContextService
    ) {
        super( new BookingAccomodation() );
    }

    public ngOnChanges(changes: SimpleChanges) {
        if(changes.model) {


        }
    }

    public ngAfterViewInit() {
        // init local componentsMap
        let map:BookingLineAccomodationComponentsMap = {
            rental_unit_assignments_ids: this.BookingServicesBookingGroupAccomodationAssignmentComponents
        };
        this.componentsMap = map;
        this.refreshAvailableRentalUnits();
    }

    public async ngOnInit() {
        this.ready = true;
    }

    public async refreshAvailableRentalUnits() {
        // reset rental units listing
        this.rentalunits.splice(0);
        try {
            // retrieve rental units available for assignment
            const data = await this.api.fetch('?get=lodging_booking_rentalunits', {
                booking_line_group_id: this.instance.booking_line_group_id,
                product_model_id: this.instance.product_model_id.id
            });
            for(let item of data) {
                this.rentalunits.push(<RentalUnitClass> item);
            }
        }
        catch(response) {
            this.api.errorFeedback(response);
        }
    }

    public async update(values:any) {
        console.log('accommodation update', values);
        super.update(values);
    }




    /**
     * Add a rental unit assignment
     */
    /*
    public async oncreateAssignment() {
        try {
            const assignment:any = await this.api.create("lodging\\sale\\booking\\SojournProductModelRentalUnitAssignement", {
                qty: 1,
                booking_id: this.booking.id,
                booking_line_group_id: this.group.id,
                sojourn_product_model_id: this.instance.id
            });
            // relay to parent
            this.updated.emit();

        }
        catch(response) {
            this.api.errorFeedback(response);
        }
    }
    */

    public async ondeleteAssignment(assignment_id: any) {
        try {
            await this.api.update(this.instance.entity, [this.instance.id], {rental_unit_assignments_ids: [-assignment_id]});
            this.instance.rental_unit_assignments_ids.splice(this.instance.rental_unit_assignments_ids.findIndex((e:any)=>e.id == assignment_id),1);
            // relay to parent
            this.updated.emit();
        }
        catch(response) {
            this.api.errorFeedback(response);
        }
    }

    public async onupdateAssignment(assignment_id:any) {
        this.updated.emit();
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
        // for each rental unit in the selection, create a new assignment
        let runningActions: Promise<any>[] = [];

        let remaining_assignments: number = this.group.nb_pers - this.instance.qty;

        for(let rental_unit_id of this.selectedRentalUnits) {
            const rentalUnit = <RentalUnitClass> this.rentalunits.find( (item) => item.id == rental_unit_id );
            if(!rentalUnit) {
                continue;
            }
            // #memo - we allow assignment value to be above strict required capacity
            /*
            if(remaining_assignments <= 0) {
                continue;
            }
            */
            let rental_unit_capacity = <number> rentalUnit.capacity;
            // compare with capacity of Product Model from SPM
            if(this.instance.product_model_id.qty_accounting_method == 'accomodation' && this.instance.product_model_id.capacity && this.instance.product_model_id.capacity < rental_unit_capacity) {
                rental_unit_capacity = <number> this.instance.product_model_id.capacity;
            }

            let assignment_qty: number = (remaining_assignments > 0)? remaining_assignments : rental_unit_capacity;

            if(assignment_qty > rental_unit_capacity) {
                assignment_qty = rental_unit_capacity;
            }

            remaining_assignments -= assignment_qty;

            const promise = this.api.create("lodging\\sale\\booking\\SojournProductModelRentalUnitAssignement", {
                rental_unit_id: rentalUnit.id,
                qty: assignment_qty,
                booking_id: this.booking.id,
                booking_line_group_id: this.group.id,
                sojourn_product_model_id: this.instance.id
            });
            runningActions.push(promise);
        }
        Promise.all(runningActions).then( () => {
            // relay refresh request to parent
            this.updated.emit();
        })
        .catch( (response) =>  {
            this.api.errorFeedback(response);
        });
        this.selectedRentalUnits.splice(0);
    }

    public addAll() {
        // unselect selected items
        this.selectedRentalUnits.splice(0);
        // select all
        for(let rentalUnit of this.rentalunits) {
            this.selectedRentalUnits.push(rentalUnit.id);
        }
        this.addSelection();
    }

    public async onclickEditAssignments() {
        const dialog = this.dialog.open(SbDialogConfirmDialog, {
            width: '33vw',
            data: {
                title: "Modification des unités locatives",
                message: "La réservation est en option ou confirmée et des consommations ont déjà été générées. \
                En cas de modifications dans les assignations d'unités locatives, les consommation seront regénérées, et le planning sera modifié en conséquence. \
                <br /><br />Confirmer cette action ?",
                yes: 'Oui',
                no: 'Non'
            }
        });

        try {
            await new Promise( async(resolve, reject) => {
                dialog.afterClosed().subscribe( async (result) => (result)?resolve(true):reject() );
            });
            this.assignments_editor_enabled = true;
        }
        catch(error) {
            // user discarded the dialog (selected 'no')
        }
    }

    public async onclickSaveAssignments() {
        console.log("resulting assignments:", this.assignmentsEditor.rentalUnitsAssignments);
        if(this.action_in_progress) {
            return;
        }
        if(this.group.is_extra) {
            return;
        }
        this.action_in_progress = true;
        let result: any[] = [];
        for(let assignment of this.assignmentsEditor.rentalUnitsAssignments) {
            result.push({rental_unit_id: assignment.rental_unit_id.id, qty: assignment.qty});
        }
        try {
            await this.api.call('/?do=lodging_booking_update-sojourn-assignment', {
                product_model_id: this.instance.product_model_id.id,
                booking_line_group_id: this.group.id,
                assignments: result
            });
            this.assignments_editor_enabled = false;
            this.action_in_progress = false;
            // snack OK
            this.updated.emit();
        }
        catch(response) {
            this.api.errorFeedback(response);
            this.action_in_progress = false;
        }
    }

    public async onclickCancelAssignments() {
        this.assignments_editor_enabled = false;
    }
}