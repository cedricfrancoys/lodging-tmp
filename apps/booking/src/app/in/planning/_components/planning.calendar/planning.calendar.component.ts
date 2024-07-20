import { Component, ChangeDetectionStrategy, ChangeDetectorRef, Output, EventEmitter, ViewChild, OnInit, OnChanges, AfterViewInit, ViewChildren, QueryList, ElementRef, AfterViewChecked, Input, SimpleChanges } from '@angular/core';

import { ChangeReservationArg } from 'src/app/model/changereservationarg';
import { HeaderDays } from 'src/app/model/headerdays';


import { ApiService, AuthService } from 'sb-shared-lib';
import { CalendarParamService } from '../../_services/calendar.param.service';
import { MatDialog, MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { MatSnackBar } from '@angular/material/snack-bar';

import { ConsumptionCreationDialog } from './_components/consumption.dialog/consumption.component';
import { HttpResponse } from '@angular/common/http';
import { max } from 'date-fns';

class RentalUnit {
    constructor(
        public id: number = 0,
        public name: string = '',
        public capacity: number = 0,
        public code: string = '',
        public status: string = '',
        public action_required: string = '',
        public order: number = 0,
        public is_accomodation: boolean = true,
        public has_children: boolean = false,
        public can_partial_rent: boolean = false,
        public color: string = ''
    ) {}
}

@Component({
    selector: 'planning-calendar',
    templateUrl: './planning.calendar.component.html',
    styleUrls: ['./planning.calendar.component.scss'],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class PlanningCalendarComponent implements OnInit, OnChanges, AfterViewInit, AfterViewChecked {
    @Input() rowsHeight: number;
    @Output() filters = new EventEmitter<ChangeReservationArg>();
    @Output() showBooking = new EventEmitter();
    @Output() showRentalUnit = new EventEmitter();

    // attach DOM element to compute the cells width
    @ViewChild('calTable') calTable: any;
    @ViewChild('calTableRefColumn') calTableRefColumn: any;
    @ViewChild('selector') selector: any;

    @ViewChildren("calTableHeadCells") calTableHeadCells: QueryList<ElementRef>;

    public loading: boolean = false;
    public headers: any;

    public headerdays: HeaderDays;

    public cellsWidth: number;

    public consumptions: any = [];
    public rental_units: any = [];
    public holidays: any = [];
    // count of rental units taken under account (not necessarily equal to `rental_units.length`)
    public count_rental_units: number = 0;

    public hovered_consumption: any;
    public hovered_rental_unit: any;
    public hovered_holidays: any;

    public hover_row_index = -1;

    public selection =  {
        is_active: false,
        left: 0,
        top: 0,
        width: 0,
        height: 0,
        cell_from: {
            left: 0,
            width: 0,
            rental_unit: {},
            date: {}
        },
        cell_to: {
            date: new Date()
        }
    };

    public mapStats: any = {
        'occupied': {},
        'capacity': {},
        'blocked': {},
        'occupancy': {},
        'arrivals_expected': {},
        'arrivals_confirmed': {},
        'departures_expected': {},
        'departures_confirmed': {}
    };

    private mousedownTimeout: any;

    // duration history as hint for refreshing cell width
    private previous_duration: number;

    private show_parents: boolean = false;
    private show_children: boolean = false;
    private today: Date;
    private today_index: string;

    constructor(
        private params: CalendarParamService,
        private api: ApiService,
        private dialog: MatDialog,
        private snack: MatSnackBar,
        private elementRef: ElementRef,
        private cd: ChangeDetectorRef) {
            this.headers = {};
            this.rental_units = [];
            this.previous_duration = 0;
            this.show_parents = (localStorage.getItem('planning_show_parents') === 'true');
            this.show_children = (localStorage.getItem('planning_show_children') === 'true');
            if(!this.show_parents && !this.show_children) {
                this.show_parents = true;
                this.show_children = true;
            }
            this.today = new Date();
            this.today_index = this.calcDateIndex(this.today);
    }

    ngOnChanges(changes: SimpleChanges): void {
        if(changes.rowsHeight)     {
            this.elementRef.nativeElement.style.setProperty('--rows_height', this.rowsHeight+'px');
        }
     }

    async ngOnInit() {

        this.params.getObservable().subscribe( () => {
            console.log('PlanningCalendarComponent cal params change', this.params);
            this.onRefresh();
        });

        this.elementRef.nativeElement.style.setProperty('--rows_height', this.rowsHeight+'px');
    }

    async ngAfterViewInit() {
    }

    /**
     * After refreshing the view with new content, adapt header and relay new cell_width, if changed
     */
    async ngAfterViewChecked() {

        if(this.calTableHeadCells) {
            for(let cell of this.calTableHeadCells) {
                this.cellsWidth = cell.nativeElement.offsetWidth;
                break;
            }
        }

        let ten_percent = this.cellsWidth * 0.1;
        if(ten_percent < 100) {
            // set width to 100px
            this.calTableRefColumn.nativeElement.style.width = '100px';
        }
        else {
            if(ten_percent > 200) {
                // set width to 200px
                this.calTableRefColumn.nativeElement.style.width = '200px';
            }
            else {
                // set width to 10%
                this.calTableRefColumn.nativeElement.style.width = '10%';
            }
        }

        // make sure ngOnChanges is triggered on sub-components
        this.cd.detectChanges();
    }

    public onRefresh() {
        console.log('onrefresh')
        this.loading = true;
        this.cd.detectChanges();
        this.show_parents = (localStorage.getItem('planning_show_parents') === 'true');
        this.show_children = (localStorage.getItem('planning_show_children') === 'true');
        if(!this.show_parents && !this.show_children) {
            this.show_parents = true;
            this.show_children = true;
        }
        // refresh the view, then run onchange
        setTimeout( async () => {
            await this.onFiltersChange();
            this.cd.reattach();
            this.loading = false;
            this.cd.detectChanges();
        });
    }

    public calcDateIndex(day: Date): string {
        let timestamp = day.getTime();
        let offset = day.getTimezoneOffset()*60*1000;
        let moment = new Date(timestamp-offset);
        return moment.toISOString().substring(0, 10);
    }

    public isWeekEnd(day:Date) {
        return (day.getDay() == 0 || day.getDay() == 6);
    }

    public isToday(day:Date) {
        return (day.getDate() == this.today.getDate() && day.getMonth() == this.today.getMonth() && day.getFullYear() == this.today.getFullYear());
    }
/*
    public isTodayIndex(day_index:string) {
        return (this.today_index == day_index);
    }
*/
    public hasConsumption(rentalUnit:RentalUnit, day_index: string):any {
        if(!this.consumptions.hasOwnProperty(rentalUnit.id) || !this.consumptions[rentalUnit.id]) {
            return false;
        }
        return this.consumptions[rentalUnit.id].hasOwnProperty(day_index);
    }

    public getConsumptions(rentalUnit:RentalUnit, day: Date): any {
        if(this.consumptions.hasOwnProperty(rentalUnit.id) && this.consumptions[rentalUnit.id]) {
            let date_index:string = this.calcDateIndex(day);
            if(this.consumptions[rentalUnit.id].hasOwnProperty(date_index)) {
                return this.consumptions[rentalUnit.id][date_index];
            }
        }
        return {};
    }

    public getDescription(consumption:any): string {
        if(consumption.hasOwnProperty('booking_id')
            && consumption['booking_id']
            && consumption['booking_id'].hasOwnProperty('description')) {
            return consumption.booking_id.description;
        }
        else if(consumption.hasOwnProperty('repairing_id')
            && consumption['repairing_id']
            && consumption['repairing_id'].hasOwnProperty('description')) {
            return consumption.repairing_id.description;
        }
        return '';
    }

    public getHolidayClasses(day: Date): string[] {
        let result = [];
        let date_index:string = this.calcDateIndex(day);
        if(this.holidays.hasOwnProperty(date_index) && this.holidays[date_index] && this.holidays[date_index].length) {
            result = this.holidays[date_index];
        }
        return result.map( (o:any) => o.type);
    }

    private async computeStats() {
        // reset values
        this.count_rental_units = 0;
        for(let rentalUnit of this.rental_units) {
            if(rentalUnit.is_accomodation) {
                if(rentalUnit.has_children && rentalUnit.can_partial_rent && this.show_children) {
                    continue;
                }
                ++this.count_rental_units;
            }
        }

        for(let date_index of this.headers.days_indexes) {
            this.mapStats['capacity'][date_index] = this.count_rental_units;
            this.mapStats['occupied'][date_index] = 0;
            this.mapStats['blocked'][date_index] = 0;
            this.mapStats['occupancy'][date_index] = 0;
            this.mapStats['arrivals_expected'][date_index] = 0;
            this.mapStats['arrivals_confirmed'][date_index] = 0;
            this.mapStats['departures_expected'][date_index] = 0;
            this.mapStats['departures_confirmed'][date_index] = 0;
        }

        const min_date_index = this.headers.days_indexes[0];
        const max_date_index = this.headers.days_indexes[this.headers.days_indexes.length - 1];

        // map for remembering assignments, to prevent considering the same rental unit several times for a same day (for capacity and occupied)
        let mapRentalUnits: any = {};

        for(let rentalUnit of this.rental_units) {
            if(rentalUnit.has_children && rentalUnit.can_partial_rent && this.show_children === true) {
                continue;
            }
            // consider only consumptions related to accommodations
            if(!rentalUnit.is_accomodation) {
                continue;
            }
            if(this.consumptions.hasOwnProperty(rentalUnit.id)) {
                for(let index in this.consumptions[rentalUnit.id]) {
                    for(let consumption of this.consumptions[rentalUnit.id][index]) {
                        let date_from = new Date(consumption.date_from);
                        let date_to = new Date(consumption.date_to);
                        for(let d = new Date(date_from.getTime()); d <= date_to; d.setDate(d.getDate() + 1)) {
                            let date_index = d.toISOString().substring(0, 10);

                            // discard indexes outside date range
                            if(date_index < min_date_index || date_index > max_date_index) {
                                continue;
                            }
                            if(!this.mapStats['capacity'].hasOwnProperty(date_index)) {
                                continue;
                            }
                            if(!mapRentalUnits.hasOwnProperty(rentalUnit.id)) {
                                mapRentalUnits[rentalUnit.id] = {};
                            }
                            if(!mapRentalUnits[rentalUnit.id].hasOwnProperty(date_index)) {
                                mapRentalUnits[rentalUnit.id][date_index] = false;
                            }
                            if(mapRentalUnits[rentalUnit.id][date_index] == true) {
                                // continue;
                            }
                            if(consumption.type == 'ooo') {
                                if(this.mapStats['capacity'][date_index]) {
                                    --this.mapStats['capacity'][date_index];
                                }
                                ++this.mapStats['blocked'][date_index];
                                // for the first date of a blocking, remove capacity for the previous day
                                if(d.getTime() == date_from.getTime()) {
                                    let prev =  new Date(d.getTime());
                                    prev.setDate(prev.getDate() - 1);
                                    let prev_date_index = this.calcDateIndex(prev);
                                    if(prev_date_index >= min_date_index) {
                                        if(this.mapStats['capacity'][prev_date_index]) {
                                            --this.mapStats['capacity'][prev_date_index];
                                        }
                                        ++this.mapStats['blocked'][prev_date_index];
                                    }
                                }
                            }
                            else {
                                if(d < date_to) {
                                    if(d.getTime() == date_from.getTime()) {
                                        ++this.mapStats['arrivals_expected'][date_index];
                                        if(consumption.booking_id.status == 'checkedin') {
                                            ++this.mapStats['arrivals_confirmed'][date_index];
                                        }
                                    }
                                    // if(!mapRentalUnits[rentalUnit.id][date_index]) {
                                        ++this.mapStats['occupied'][date_index];
                                    // }
                                }
                                else {
                                    ++this.mapStats['departures_expected'][date_index];
                                    if(['checkedout', 'invoiced', 'debit_balance', 'credit_balance', 'balanced'].indexOf(consumption.booking_id.status) > -1) {
                                        ++this.mapStats['departures_confirmed'][date_index];
                                    }
                                }
                            }
                            mapRentalUnits[rentalUnit.id][date_index] = true;
                        }
                    }
                }
            }
        }

        for(let date_index of this.headers.days_indexes) {
            this.mapStats['occupancy'][date_index] = 100;
            if(this.mapStats['capacity'][date_index] > 0) {
                let occupancy = Math.round(100 * this.mapStats['occupied'][date_index] / this.mapStats['capacity'][date_index]);
                if(occupancy > 100) {
                    occupancy = 100;
                }
                this.mapStats['occupancy'][date_index] = occupancy;
            }
        }

        // console.log(this.mapStats);

    }

    private async onFiltersChange() {
        this.createHeaderDays();

        try {
            const domain: any[] = JSON.parse(JSON.stringify(this.params.rental_units_filter));
            if(!domain.length) {
                domain.push([['can_rent', '=', true], ["center_id", "in", this.params.centers_ids]]);
            }
            else {
                for(let i = 0, n = domain.length; i < n; ++i) {
                    domain[i].push(["center_id", "in",  this.params.centers_ids]);
                }
            }
            const rental_units = await this.api.collect(
                "lodging\\realestate\\RentalUnit",
                domain,
                Object.getOwnPropertyNames(new RentalUnit()),
                'center_id,order', 'asc', 0, 500
            );
            if(rental_units) {
                this.rental_units = rental_units;
            }
        }
        catch(response) {
            console.warn('unable to fetch rental units', response);
        }


        if(this.params.centers_ids.length <= 0) {
            this.loading = false;
            return;
        }

        try {
            let holidays:any = await this.api.collect(
                "calendar\\Holiday",
                [
                    [ [ "date_from", ">=",  this.params.date_from], [ "date_to", "<=",  this.params.date_to ] ],
                    [ [ "date_from", ">=",  this.params.date_from], [ "date_from", "<=",  this.params.date_to ] ],
                    [ [ "date_to", ">=",  this.params.date_from], [ "date_to", "<=",  this.params.date_to ] ],
                ],
                ['name', 'date_from', 'date_to', 'type'],
                'id', 'asc', 0, 100
            );
            if(holidays) {
                for(let holiday of holidays) {
                    holiday['date_from_int']  = parseInt(holiday.date_from.substring(0, 10).replace(/-/gi, ''), 10);
                    holiday['date_to_int'] = parseInt(holiday.date_to.substring(0, 10).replace(/-/gi, ''), 10);
                }
                this.holidays = {};
                let d = new Date();
                for (let d = new Date(this.params.date_from.getTime()); d <= this.params.date_to; d.setDate(d.getDate() + 1)) {
                    let date_index:string = this.calcDateIndex(d);
                    let date_int  = parseInt(date_index.replace(/-/gi, ''), 10);
                    this.holidays[date_index] = holidays.filter( (h:any) => (date_int >= h['date_from_int'] && date_int <= h['date_to_int']) );
                }
            }
        }
        catch(response: any) {
            console.warn('unable to fetch holidays', response);
            // if a 403 response is received, we assume that the user is not identified: redirect to /auth
            if(response.status == 403) {
                window.location.href = '/auth';
            }
        }

        try {
            this.consumptions = await this.api.fetch('?get=lodging_consumption_map', {
                // #memo - all dates are considered UTC
                date_from: this.calcDateIndex(this.params.date_from),
                date_to: this.calcDateIndex(this.params.date_to),
                centers_ids: JSON.stringify(this.params.centers_ids)
            });
            await this.computeStats();
        }
        catch(response: any ) {
            console.warn('unable to fetch rental units', response);
            // if a 403 response is received, we assume that the user is not identified: redirect to /auth
            if(response.status == 403) {
                window.location.href = '/auth';
            }
        }

    }


    /**
     * Recompute content of the header.
     *
     * Convert to following structure :
     *
     * headers.months:
     *    months[]
     *        {
     *            month:
     *            days:
     *        }
     *
     * headers.days: date[]
     */
    private createHeaderDays() {

        if(this.previous_duration != this.params.duration) {
            // temporarily reset cellsWidth to an arbitrary ow value
            this.cellsWidth = 12;
        }

        this.previous_duration = this.params.duration;

        // reset headers
        this.headers = {
            months: [],
            days: [],
            days_indexes: []
        };

        let months:any = {};
        // pass-1 assign dates
        for (let i = 0; i < this.params.duration; i++) {
            let date = new Date(this.params.date_from.getTime());
            date.setDate(date.getDate() + i);
            this.headers.days.push(date);
            this.headers.days_indexes.push(this.calcDateIndex(date))
            let month_index = date.getFullYear()*100+date.getMonth();
            if(!months.hasOwnProperty(month_index)) {
                months[month_index] = [];
            }
            months[month_index].push(date);
        }

        // pass-2 assign months (in order)
        let months_array = Object.keys(months).sort( (a: any, b: any) => (a - b) );
        for(let month of months_array) {
            this.headers.months.push(
                {
                    date: months[month][0],
                    month: month,
                    days: months[month]
                }
            );
        }

    }

    public onhoverBooking(consumption:any) {
        // relay hovered consumption to navbar
        this.hovered_consumption = consumption;
    }

    public onhoverDate(day:Date) {
        let result;
        if(day) {
            let date_index:string = this.calcDateIndex(day);
            if(this.holidays.hasOwnProperty(date_index) && this.holidays[date_index].length) {
                result = this.holidays[date_index];
            }
        }
        this.hovered_holidays = result;
    }

    public onSelectedBooking(event: any) {
        clearTimeout(this.mousedownTimeout);
        this.showBooking.emit(event);
    }

    public onSelectedRentalUnit(rental_unit: any) {
        clearTimeout(this.mousedownTimeout);
        this.showRentalUnit.emit(rental_unit);
    }

    public onhoverDay(rental_unit: any, day:Date) {
        this.hovered_rental_unit = rental_unit;

        if(day) {
            let date_index:string = this.calcDateIndex(day);
            if(this.holidays.hasOwnProperty(date_index) && this.holidays[date_index].length) {
                this.hovered_holidays = this.holidays[date_index];
            }
        }
        else {
            this.hovered_holidays = undefined;
        }
    }

    public onhoverRentalUnit(rental_unit: any) {
        this.hovered_rental_unit = rental_unit;
    }

    public onmouseleaveTable() {
        clearTimeout(this.mousedownTimeout);
        this.selection.is_active = false;
        this.selection.width = 0;
    }

    public onmouseup() {
        clearTimeout(this.mousedownTimeout);

        if(this.selection.is_active) {
            console.log('is active');
            // make from and to right
            let rental_unit:any = this.selection.cell_from.rental_unit;
            let from:any = this.selection.cell_from;
            let to:any = this.selection.cell_to;
            if(this.selection.cell_to.date < this.selection.cell_from.date) {
                from = this.selection.cell_to;
                to = this.selection.cell_from;
            }
            // check selection for existing consumption
            let valid = true;
            let diff = (<Date>this.selection.cell_to.date).getTime() - (<Date>this.selection.cell_from.date).getTime();
            let days = Math.abs(Math.floor(diff / (60*60*24*1000)))+1;
            // do not check last day : overlapse is allowed if checkout is before checkin
            for (let i = 0; i < days-1; i++) {
                let currdate = new Date(from.date.getTime());
                currdate.setDate(currdate.getDate() + i);
                if(this.hasConsumption(rental_unit, this.calcDateIndex(currdate))) {
                    valid = false;
                    break;
                }
            }
            if(!valid || !from.rental_unit) {
                this.selection.is_active = false;
                this.selection.width = 0;
                return;
            }
            else {
                // open dialog for requesting action dd

                const dialogRef = this.dialog.open(ConsumptionCreationDialog, {
                    width: '50vw',
                    data: {
                        rental_unit: from.rental_unit.name,
                        rental_unit_id: from.rental_unit.id,
                        date_from: from.date,
                        date_to: to.date
                    }
                });

                dialogRef.afterClosed().subscribe( async (values) => {
                    if(values) {
                        if(values.type && values.type == 'book') {
                            try {
                                // let date_from = new Date(values.date_from.getTime()-values.date_from.getTimezoneOffset()*60*1000);
                                let date_from = (new Date(values.date_from.getTime()));
                                let date_to = (new Date(values.date_to.getTime()));
                                date_from.setHours(0,-values.date_from.getTimezoneOffset(),0,0);
                                date_to.setHours(0,-values.date_to.getTimezoneOffset(),0,0);
                                await this.api.call('?do=lodging_booking_plan-option', {
                                    date_from: date_from.toISOString(),
                                    date_to: date_to.toISOString(),
                                    rental_unit_id: values.rental_unit_id,
                                    customer_identity_id: values.customer_identity_id,
                                    no_expiry: values.no_expiry,
                                    free_rental_units: values.free_rental_units
                                });

                                this.onRefresh();
                            }
                            catch(response) {
                                this.api.errorFeedback(response);
                            }
                        }
                        else if(values.type && values.type == 'ooo') {
                            try {
                                let date_from = (new Date(values.date_from.getTime()));
                                let date_to = (new Date(values.date_to.getTime()));
                                date_from.setHours(0,-values.date_from.getTimezoneOffset(),0,0);
                                date_to.setHours(0,-values.date_to.getTimezoneOffset(),0,0);
                                await this.api.call('?do=lodging_booking_plan-repair', {
                                    date_from: date_from.toISOString(),
                                    date_to: date_to.toISOString(),
                                    rental_unit_id: values.rental_unit_id,
                                    description: (values.description.length)?values.description:'Blocage via planning'
                                });

                                this.onRefresh();
                            }
                            catch(response) {
                                this.snack.open('Ce blocage est en conflit avec des consommations existantes.', 'ERREUR');
                                // this.api.errorFeedback(response);
                            }
                        }

                    }
                });

            }
        }

        this.selection.is_active = false;
        this.selection.width = 0;

    }

    public onmousedown($event: any, rental_unit: any, day: any) {
        // start selection with a 100ms delay to avoid confusion with booking selection
        this.mousedownTimeout = setTimeout( () => {
            let table = this.calTable.nativeElement.getBoundingClientRect();
            let cell = $event.target.getBoundingClientRect();

            this.selection.top = cell.top - table.top;
            this.selection.left = cell.left - table.left + this.calTable.nativeElement.offsetLeft;

            this.selection.width = cell.width;
            this.selection.height = cell.height;

            this.selection.cell_from.left = this.selection.left;
            this.selection.cell_from.width = cell.width;
            this.selection.cell_from.date = day;
            this.selection.cell_from.rental_unit = rental_unit;

            this.selection.is_active = true;
        }, 100);
    }

    public onmouseover($event: any, day:any) {
        if(this.selection.is_active) {
            // selection between start and currently hovered cell
            let table = this.calTable.nativeElement.getBoundingClientRect();
            let cell = $event.target.getBoundingClientRect();

            this.selection.cell_to.date = day;

            // diff between two dates
            let diff = (<Date>this.selection.cell_to.date).getTime() - (<Date>this.selection.cell_from.date).getTime();
            let days = Math.abs(Math.floor(diff / (60*60*24*1000)))+1;

            this.selection.width = this.selection.cell_from.width * days

            if(this.selection.cell_from.date > this.selection.cell_to.date) {
                this.selection.left = cell.left - table.left + this.calTable.nativeElement.offsetLeft;
            }
            else {
                this.selection.left = this.selection.cell_from.left;
            }
        }
    }

    public preventDrag($event:any) {
        $event.preventDefault();
    }
}