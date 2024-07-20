import { Component, AfterContentInit, OnInit, NgZone, ChangeDetectorRef } from '@angular/core';
import { ActivatedRoute, Router, RouterEvent, NavigationEnd } from '@angular/router';
import { MatDialog, MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { ApiService, EnvService, AuthService, ContextService } from 'sb-shared-lib';
import { MatSnackBar } from '@angular/material/snack-bar';
import { FormControl, Validators } from '@angular/forms';
import { UserClass } from 'sb-shared-lib/lib/classes/user.class';
import { debounceTime, filter } from 'rxjs/operators';

class Booking {
    constructor(
        public id: number = 0,
        public name: string = '',
        public date_from: Date = new Date(),
        public date_to: Date  = new Date(),
        public customer_id: number = 0,
        public center_id: number = 0,
        public type_id: number = 0,
        public contacts_ids: number[] = []
    ) {}
}

class Customer {
    constructor(
        public id: number = 0,
        public name: string = '',
        public email: string = '',
        public email_secondary: string = '',
        public lang_id: number = 0
    ) {}
}

class Center {
    constructor(
        public id: number = 0,
        public name: string = '',
        public email: string = '',
        public organisation_id: number = 0,
        public template_category_id: number = 0
    ) {}
}

class CenterOffice {
    constructor(
        public id: number = 0,
        public name: string = '',
        public email: string = '',
        public code: number = 0,
    ) {}
}

class Organisation {
    constructor(
        public id: number = 0,
        public name: string = '',
        public email: string = '',
        public signature: string = ''
    ) {}
}

class Contact {
    constructor(
        public id: number = 0,
        public name: string = '',
        public email: string = '',
        public phone: string = ''
    ) {}
}

interface vmModel {
    lang: {
        formControl:  FormControl,
    },
    mode: {
        formControl:  FormControl,
    },
    title: {
        formControl:  FormControl,
    },
    message: {
        formControl:  FormControl,
    }
    sender: {
        addresses:    string [],
        formControl:  FormControl
    },
    recipient: {
        addresses:    string [],
        formControl:  FormControl
    },
    recipients: {
        addresses:    string [],
        formControl:  FormControl
    },
    attachments: {
        items:        any[]
    }
};

@Component({
  selector: 'booking-funding-remind',
  templateUrl: 'remind.component.html',
  styleUrls: ['remind.component.scss']
})
export class BookingFundingRemindComponent implements OnInit, AfterContentInit {

    public showSbContainer: boolean = false;
    public selectedTabIndex:number = 0;
    public loading = true;
    public is_sent = false;

    public user: UserClass = null;
    public booking_id: number;
    public funding_id: number;

    public organisation: any = new Organisation();
    public center: any = new Center();
    public office: any = new CenterOffice();
    public booking: any = new Booking();
    public customer: any = new Customer();
    public contacts: any[] = [];


    public languages: any[] = [];

    // #memo - we need to keep both the ID and the code of the selected language
    private lang_id: number = 0;

    public lang: string = '';
    public mode:string = 'simple';
    public title: string = '';
    public message: string = '';
    public sender: string = '';
    // address of email main recipient
    public recipient: string = '';
    // list of secondary recipients (addresses comma separated)
    public recipients: string[] = [];



    public vm: vmModel;


    constructor(
        private dialog: MatDialog,
        private api: ApiService,
        private auth: AuthService,
        private env: EnvService,
        private router: Router,
        private cd: ChangeDetectorRef,
        private route: ActivatedRoute,
        private context:ContextService,
        private snack: MatSnackBar,
        private zone: NgZone) {


        this.vm = {
            lang: {
                formControl:    new FormControl('fr'),
            },
            mode: {
                formControl:    new FormControl('simple'),
            },
            title: {
                formControl:    new FormControl('', Validators.required)
            },
            message: {
                formControl:    new FormControl('', Validators.required),
            },
            sender: {
                addresses:      [],
                formControl:    new FormControl('', [Validators.required, Validators.pattern("^[a-z0-9._%+-]+@[a-z0-9.-]+\\.[a-z]{2,8}$")])
            },
            recipient: {
                addresses:      [],
                formControl:    new FormControl('', [Validators.required, Validators.email])
            },
            recipients: {
                addresses:      [],
                formControl:    new FormControl()
            },
            attachments: {
                items:          []
            }
        };
    }

    /**
     * Set up callbacks when component DOM is ready.
     */
    public ngAfterContentInit() {
        this.loading = false;

        // bind VM to model
        this.vm.lang.formControl.valueChanges.subscribe( (lang:string) => this.refreshLang(this.getLangId(lang)));
        this.vm.mode.formControl.valueChanges.subscribe( (mode:string) => this.mode = mode);
        this.vm.title.formControl.valueChanges.pipe(debounceTime(300)).subscribe( (title:string) => this.title = title);
        this.vm.message.formControl.valueChanges.pipe(debounceTime(500)).subscribe( (message:string) => this.message = message);
        this.vm.sender.formControl.valueChanges.subscribe( (sender:string) => this.sender = sender);
        this.vm.recipient.formControl.valueChanges.subscribe( (recipient:string) => this.recipient = recipient);
        this.vm.recipients.formControl.valueChanges.subscribe( (recipients:string[]) => this.recipients = recipients);
    }

    /**
     * invoked on lang change
     */
    private refreshLang(lang_id:number) {
        if(lang_id != this.lang_id) {
            const found = this.languages.find(elem => elem.id == lang_id);
            if(found) {
                this.lang_id = found.id;
                this.lang = found.code;
                this.fetchTemplates();
            }
        }
    }

    public ngOnInit() {
        this.loadLanguages();

        this.auth.getObservable().subscribe( async (user: UserClass) => {
            this.user = user;
            this.refreshSenderAddresses();
        });

        // fetch the booking ID from the route
        this.route.params.subscribe( async (params) => {
            if(params) {

                try {

                    if(params.hasOwnProperty('booking_id')){
                        this.booking_id = <number> parseInt(params['booking_id'], 10);
                        await this.loadBooking();
                    }

                    if(params.hasOwnProperty('funding_id')){
                        this.funding_id = <number> parseInt(params['funding_id'], 10);
                    }

                    // #memo - this is only necessary when directly browsing to the URL /booking/:id/invoice/:id
                    // relay change to context (to display sidemenu panes according to current object)
                    this.context.change({
                        context_only: true,   // do not change the view
                        context: {
                            entity: 'lodging\\sale\\booking\\Booking',
                            type: 'form',
                            purpose: 'view',
                            domain: ['id', '=', this.booking_id]
                        }
                    });

                }
                catch(error) {
                    console.warn(error);
                }
            }
        });
    }

    /**
     * Fetch templates that apply on current booking (center and related category).
     *
     * We should received attachments, invoice.mail.subject, invoice.mail.body + organisation signature
     */
    private async fetchTemplates() {
        console.log('re-fetch templates', this.center);
        try {
            const templates = await this.api.collect("lodging\\communication\\Template", [
                ['category_id', '=', this.center.template_category_id],
                ['type', '=', 'funding'],
                ['code', '=', 'reminder']
            ],
            ['parts_ids', 'attachments_ids'], 'id', 'asc', 0, 1, this.lang);

            if(templates && templates.length) {
                // we should have received a single template
                let template = templates[0];

                const parts = await this.api.collect("lodging\\communication\\TemplatePart", ['id', 'in', template['parts_ids']], ['name', 'value'], 'id', 'asc', 0, 10, this.lang);

                for(let part of parts) {
                    if(part.name == 'subject') {
                        let title = '';
                        if(part.hasOwnProperty('value') && part.value.length) {
                            // strip html nodes
                            title = part.value.replace(/<[^>]*>?/gm, '');
                        }

                        const date_from = new Date(this.booking.date_from);
                        const date_to = new Date(this.booking.date_to);

                        if(title.indexOf('{booking}') == -1) {
                            // prepend the booking code to make sure it is always present
                            title = '{booking}' + ' - ' + title;
                        }
                        if(title.indexOf('{date_from}') == -1) {
                            // append date_from if not present
                            title = title + ' - ' + '{date_from}';
                        }
                        if(title.indexOf('{date_to}') == -1) {
                            // append date_to if not present
                            title = title + '-' + '{date_to}';
                        }

                        // apply replacements
                        let str_date_from = date_from.getDate().toString().padStart(2, '0') + '/' + (date_from.getMonth()+1).toString().padStart(2, '0') + '/' + date_from.getFullYear();
                        let str_date_to = date_to.getDate().toString().padStart(2, '0') + '/' + (date_to.getMonth()+1).toString().padStart(2, '0') + '/' + date_to.getFullYear();

                        title = title.replace("{booking}", this.booking.name);
                        title = title.replace("{center}", this.center.name);
                        title = title.replace("{date_from}", str_date_from);
                        title = title.replace("{date_to}", str_date_to);

                        this.vm.title.formControl.setValue(title);
                    }
                    else if(part.name == 'body') {
                        this.vm.message.formControl.setValue(part.value);
                    }
                }

                let attachment_types: any = ['all'];
                if(this.booking.type_id == 1) {
                    attachment_types.push('individuals');
                }
                else if(this.booking.type_id == 4) {
                    attachment_types.push('schools');
                }
                else if(this.booking.type_id == 6) {
                    attachment_types.push('groups');
                }

                // reset attachments list
                this.vm.attachments.items.splice(0, this.vm.attachments.items.length);
                const attachments = await this.api.collect("lodging\\communication\\TemplateAttachment", [['id', 'in', template['attachments_ids']], ['attachment_type', 'in', attachment_types], ['lang_id', '=', this.lang_id]], ['name', 'document_id.name', 'document_id.hash'], 'id', 'asc', 0, 20, this.lang);
                for(let attachment of attachments) {
                    this.vm.attachments.items.push(attachment)
                }
            }
        }
        catch(error) {
            console.log(error);
        }
    }

    private async loadLanguages() {
        const environment:any = await this.env.getEnv();
        this.lang = environment.lang;
        const result:Array<any> = <Array<any>> await this.api.collect("core\\Lang", [], ['id', 'code', 'name'], 'name', 'asc', 0, 100, environment.locale);
        if(result && result.length) {
            this.languages = result;
            for(let lang of this.languages) {
                if(lang.code == this.lang) {
                    this.lang_id = lang.id;
                }
            }
        }
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
            if(this.booking.customer_id) {
                await this.loadCustomer();
            }
            if(this.booking.contacts_ids && this.booking.contacts_ids.length) {
                await this.loadContacts();
                this.refreshRecipientAddresses();
            }
            if(this.booking.center_id) {
                await this.loadCenter();
                this.refreshSenderAddresses();
                // load templates
                this.fetchTemplates();
            }
        }
    }

    private async loadCustomer() {
        const result = <Array<any>> await this.api.read("lodging\\sale\\customer\\Customer", [this.booking.customer_id], Object.getOwnPropertyNames(new Customer()));
        if(result && result.length) {
            const item:any = result[0];
            let customer:any = new Customer();
            for(let field of Object.getOwnPropertyNames(customer) ) {
                if(item.hasOwnProperty(field)) {
                    customer[field] = item[field];
                }
            }
            this.customer = <Customer> customer;
            this.refreshRecipientAddresses();
            if(this.customer.lang_id != this.lang_id) {
                this.refreshLang(this.customer.lang_id);
            }
        }
    }

    private async loadContacts() {
        const result = <Array<any>> await this.api.read("sale\\booking\\Contact", this.booking.contacts_ids, Object.getOwnPropertyNames(new Contact()));
        if(result && result.length) {
            // reset current list
            this.contacts = [];
            for(let item of result) {
                let contact:any = new Contact();
                for(let field of Object.getOwnPropertyNames(contact) ) {
                    if(contact.hasOwnProperty(field)) {
                    contact[field] = item[field];
                    }
                }
                this.contacts.push(contact);
            }
        }
    }

    private async loadCenter() {
        const result = <Array<any>> await this.api.read("lodging\\identity\\Center", [this.booking.center_id], Object.getOwnPropertyNames(new Center()));
        if(result && result.length) {
            const item:any = result[0];
            let center:any = new Center();
            for(let field of Object.getOwnPropertyNames(center) ) {
                if(item.hasOwnProperty(field)) {
                center[field] = item[field];
                }
            }
            this.center = <Center> center;
            if(this.center.organisation_id) {
                await this.loadOrganisation();
            }
            if(this.center.use_office_details && this.center.center_office_id) {
                await this.loadCenterOffice();
            }
        }
    }

    private async loadCenterOffice() {
        const result = <Array<any>> await this.api.read("lodging\\identity\\CenterOffice", [this.center.center_office_id], Object.getOwnPropertyNames(new CenterOffice()));
        if(result && result.length) {
            const item:any = result[0];
            let office:any = new CenterOffice();
            for(let field of Object.getOwnPropertyNames(office) ) {
                if(item.hasOwnProperty(field)) {
                office[field] = item[field];
                }
            }
            this.office = <CenterOffice> office;
        }
    }

    private async loadOrganisation() {
        const result = <Array<any>> await this.api.read("identity\\Identity", [this.center.organisation_id], Object.getOwnPropertyNames(new Organisation()));
        if(result && result.length) {
            let item = result[0];
            let organisation:any = new Organisation();
            for(let field of Object.getOwnPropertyNames(organisation) ) {
                if(result.hasOwnProperty(field)) {
                    organisation[field] = item[field];
                }
            }
            this.organisation = <Organisation> organisation;
        }
    }

    public refreshSenderAddresses() {

        // reset array
        this.vm.sender.addresses = [];
        this.vm.sender.formControl.reset();

        // 1) email of Center's Office, if any
        if(this.center.use_office_details && this.office && this.office.email.length) {
            if(!this.vm.sender.addresses.includes(this.office.email)) {
                this.vm.sender.addresses.push(this.office.email);
            }
        }

        // 2) email of the organisation
        if(this.organisation.email && this.organisation.email.length) {
            if(!this.vm.sender.addresses.includes(this.organisation.email)) {
                this.vm.sender.addresses.push(this.organisation.email);
            }
        }

        // 3) email of the center
        if(this.center.email && this.center.email.length) {
            if(!this.vm.sender.addresses.includes(this.center.email)) {
                this.vm.sender.addresses.push(this.center.email);
            }
        }

        // 4) email of current user
        if(this.user) {
            if(!this.vm.sender.addresses.includes(this.user.login)) {
                this.vm.sender.addresses.push(this.user.login);
            }
        }

        if(this.vm.sender.addresses.length == 1) {
            this.vm.sender.formControl.setValue(this.vm.sender.addresses[0]);
        }

    }

    private async refreshRecipientAddresses() {
        console.debug('BookingQuoteComponent::refreshRecipientAddresses', this.customer, this.contacts);

        // reset array
        this.vm.recipient.addresses = [];
        this.vm.recipient.formControl.reset();

        // customer address
        if(this.customer && this.customer.email && this.customer.email.length) {
            if(!this.vm.recipient.addresses.includes(this.customer.email)) {
                this.vm.recipient.addresses.push(this.customer.email);
                this.vm.recipient.formControl.setValue(this.customer.email);
            }
            if(!this.vm.recipients.addresses.includes(this.customer.email)) {
                this.vm.recipients.addresses.push(this.customer.email);
            }
        }

        if(this.customer && this.customer.email_secondary && this.customer.email_secondary.length) {
            if(!this.vm.recipient.addresses.includes(this.customer.email_secondary)) {
                this.vm.recipient.addresses.push(this.customer.email_secondary);
            }
            if(!this.vm.recipients.addresses.includes(this.customer.email_secondary)) {
                this.vm.recipients.addresses.push(this.customer.email_secondary);
            }
        }

        // emails of the contacts
        if(this.contacts && this.contacts.length) {
            for(let contact of this.contacts) {
                if(!contact.email.length) {
                    continue;
                }
                if(!this.vm.recipient.addresses.includes(contact.email)) {
                    this.vm.recipient.addresses.push(contact.email);
                    if(contact.type == 'booking') {
                        this.vm.recipient.formControl.setValue(contact.email);
                    }
                }
                if(!this.vm.recipients.addresses.includes(contact.email)) {
                    this.vm.recipients.addresses.push(contact.email);
                }
            }
        }

        // no email found
        if(!this.vm.recipient.addresses.length) {
            // for testing
            this.vm.recipient.addresses.push(this.user.login);
        }

        if(this.vm.recipient.addresses.length == 1) {
            this.vm.recipient.formControl.setValue(this.vm.recipient.addresses[0]);
        }

    }

    public onRemoveAttachment(attachment:any) {
        this.vm.attachments.items.splice(this.vm.attachments.items.indexOf(attachment), 1);
    }

    public getLangId(lang:string) {
        const found = this.languages.find(elem => elem.code == lang);
        return (found)?found.id:0;
    }

    public getLangCode(lang_id:number) {
        const found = this.languages.find(elem => elem.id == lang_id);
        return (found)?found.code:'';
    }

    public getLangName(lang_id:number) {
        const found = this.languages.find(elem => elem.id == lang_id);
        return (found)?found.name:'';
    }

    public async onSend() {
        /*
            Validate values (otherwise mark fields as invalid)
        */

        let is_error = false;

        if(this.vm.sender.formControl.invalid || this.vm.sender.formControl.value.length == 0) {
            console.log('sender');
            this.vm.sender.formControl.markAsTouched();
            is_error = true;
        }

        if(this.vm.recipient.formControl.invalid || this.vm.recipient.formControl.value.length == 0) {
            this.vm.recipient.formControl.markAsTouched();
            is_error = true;
        }

        if(this.vm.title.formControl.invalid || this.vm.title.formControl.value.length == 0) {
            this.vm.title.formControl.markAsTouched();
            console.log('title');
            is_error = true;
        }

        if(this.vm.message.formControl.invalid || this.vm.message.formControl.value.length == 0) {
            console.log('message');
            this.vm.message.formControl.markAsTouched();
            is_error = true;
        }

        if(is_error) {
            console.log('error detected, not sending')
            return;
        }

        try {
            this.loading = true;
            const response:any = await this.api.call('?do=lodging_booking_send-funding-reminder', {
                funding_id: this.funding_id,
                sender_email: this.sender,
                recipient_email: this.recipient,
                recipients_emails: this.recipients,
                title: this.title,
                message: this.message,
                lang: this.lang
            });
            this.is_sent = true;
            this.snack.open("Rappel envoyé avec succès.");
            this.loading = false;
        }
        catch(response:any) {
            let message: string = 'Erreur inconnue';
            if(response.error && response.error.errors) {
                const codes = Object.keys(response.error.errors);
                if(codes.length) {
                switch(codes[0]) {
                    case 'NOT_ALLOWED':
                        message = 'Opération non autorisée';
                        break;
                }
                }
            }
            setTimeout( () => {
                this.loading = false;
                this.snack.open(message, "Erreur");
            }, 500);
        }

    }

    public onclickBooking() {
        let descriptor:any = {
            context_silent: true, // do not update sidebar
            context: {
                entity: 'lodging\\sale\\booking\\Booking',
                type: 'form',
                name: 'default',
                domain: ['id', '=', this.booking_id],
                mode: 'view',
                purpose: 'view',
                display_mode: 'popup',
                callback: (data:any) => {
                    // restart angular lifecycles
                    this.cd.reattach();
                }
            }
        };

        // prevent angular lifecycles while a context is open
        this.cd.detach();
        this.context.change(descriptor);
    }

    public onclickCustomer() {
        let descriptor:any = {
            context_silent: true, // do not update sidebar
            context: {
                entity: 'sale\\customer\\Customer',
                type: 'form',
                name: 'default',
                domain: ['id', '=', this.booking.customer_id],
                mode: 'view',
                purpose: 'view',
                display_mode: 'popup',
                callback: (data:any) => {
                    // restart angular lifecycles
                    this.cd.reattach();
                }
            }
        };

        // prevent angular lifecycles while a context is open
        this.cd.detach();
        this.context.change(descriptor);
    }
}