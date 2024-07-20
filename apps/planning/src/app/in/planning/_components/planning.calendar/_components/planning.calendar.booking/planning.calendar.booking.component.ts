import { Component, Input, Output, ElementRef, EventEmitter, OnInit, OnChanges, SimpleChanges, ViewChild, AfterViewInit, ChangeDetectorRef } from '@angular/core';
import { es } from 'date-fns/locale';

const millisecondsPerDay:number = 24 * 60 * 60 * 1000;

@Component({
  selector: 'planning-calendar-booking',
  templateUrl: './planning.calendar.booking.component.html',
  styleUrls: ['./planning.calendar.booking.component.scss']
})
export class PlanningCalendarBookingComponent implements OnInit, OnChanges  {
    @Input()  day: Date;
    @Input()  consumption: any;
    @Input()  width: number;
    @Input()  height: number;
    @Output() hover = new EventEmitter<any>();
    @Output() selected = new EventEmitter<any>();

    constructor(
        private elementRef: ElementRef
    ) {}

    ngOnInit() { }

    ngOnChanges(changes: SimpleChanges) {
        if (changes.consumption || changes.width) {
            this.datasourceChanged();
        }
        if(changes.height) {
            this.elementRef.nativeElement.style.setProperty('--height', this.height+'px');
        }
    }

    /**
     * Convert a string-formated time to a unix timestamp-like value (i.e the number of seconds elapsed since midnight).
     *
     */
    private getTime(time:string) : number {
        let parts = time.split(':');
        return (parseInt(parts[0])*3600) + (parseInt(parts[1])*60) + parseInt(parts[2]);
    }

    /**
     * Provide the absolute value, in days, of the difference between two dates.
    */
    private calcDiff(date1: Date, date2: Date) : number {
        let start = new Date(date1.getTime());
        start.setMinutes(start.getMinutes() - start.getTimezoneOffset());
        let end = new Date(date2.getTime());
        end.setMinutes(end.getMinutes() - end.getTimezoneOffset());
        let diff = Math.abs(start.getTime() - end.getTime());
        return Math.round(diff / (1000 * 3600 * 24));
    }

    private calcDateInt(day: Date) {
        let timestamp = day.getTime();
        let offset = day.getTimezoneOffset()*60*1000;
        let moment = new Date(timestamp-offset);
        return parseInt(moment.toISOString().substring(0, 10).replace(/-/g, ''), 10);
    }

    private isSameDate(date1:Date, date2:Date) : boolean {
        try {
            return (this.calcDateInt(date1) == this.calcDateInt(date2));
        }
        catch(error) {
            // ignore errors
        }
        return false;
    }

    private datasourceChanged() {
        const unit = this.width/(24*3600);

        // offset since the start of the current day
        let offset:number = 0;
        let width:string = '100%';

        // ignore invalid consumptions
        if(!this.consumption || Object.keys(this.consumption).length == 0) {
            return;
        }

        // #todo - we shoud have info about last visible date

        let date_from = new Date(this.consumption.date_from);
        let date_to = new Date(this.consumption.date_to);
        let date = new Date(this.consumption.date);

        if(date_from.getTime() < date.getTime()) {
            // #memo - offset is left to 0
            let time_to = this.getTime(this.consumption.schedule_to);
            let days = this.calcDiff(date_to, date);
            width = Math.abs(unit * ((24*3600*days) + (time_to))).toString() + 'px';
        }
        else {
            let time_to = this.getTime(this.consumption.schedule_to);
            let time_from = this.getTime(this.consumption.schedule_from);
            offset  = unit * time_from;
            let days = this.calcDiff(date_to, date_from) - 1;
            width = Math.abs(unit * (((24*3600)-time_from) + (24*3600*days) + (time_to))).toString() + 'px';
        }

        this.elementRef.nativeElement.style.setProperty('--height', this.height+'px');
        // #memo - width can be expressed in px or %
        this.elementRef.nativeElement.style.setProperty('--width', width);
        this.elementRef.nativeElement.style.setProperty('--offset', offset+'px');
    }


    public onShowBooking(booking: any) {
       this.selected.emit(booking);
    }

    public onEnterConsumption(consumption:any) {
        this.hover.emit(consumption);
    }

    public onLeaveConsumption(consumption:any) {
        this.hover.emit();
    }

    public getColor() {
        const colors: any = {
            yellow: '#ff9633',
            turquoise: '#0fc4a7',
            green: '#0fa200',
            blue: '#0288d1',
            violet: '#9575cd',
            red: '#c80651',
            grey: '#988a7d',
            purple: '#7733aa'
        };

        if(this.consumption.type == 'ooo') {
            return colors['red'];
        }
        else if(this.consumption.booking_id?.status == 'quote') {
            // #memo - reverted to quote but without releasing the rental units
            return colors['grey'];
        }
        else if(this.consumption.booking_id?.status == 'option') {
            return colors['blue'];
        }
        else if(this.consumption.booking_id?.status == 'confirmed') {
            return colors['yellow'];
        }
        else if(this.consumption.booking_id?.status == 'validated') {
            return colors['green'];
        }
        else if(this.consumption.booking_id?.status == 'checkedin') {
            return colors['turquoise'];
        }
        else if(this.consumption.booking_id?.status == 'checkedout') {
            return colors['grey'];
        }
        // invoiced and beyond
        return colors['purple'];
    }

    public getIcon() {
        if(this.consumption.type == 'ooo') {
            return 'block';
        }
        else if(this.consumption.booking_id?.status == 'quote') {
            // #memo - reverted to quote but without releasing the rental units
            return 'question_mark';
        }
        else if(this.consumption.booking_id?.status == 'option') {
            return 'question_mark';
        }
        else if(this.consumption.booking_id?.status == 'confirmed') {
            if(this.consumption.booking_id?.payment_status == 'paid') {
                return 'money_off';
            }
            else {
                return 'attach_money';
            }
        }
        else if(this.consumption.booking_id?.status == 'validated') {
            return 'check';
        }
        else if(this.consumption.booking_id?.status == 'invoiced') {
            if(this.consumption.booking_id?.payment_status == 'paid') {
                return 'money_off';
            }
            else {
                return 'attach_money';
            }
        }
        return '';
    }

}