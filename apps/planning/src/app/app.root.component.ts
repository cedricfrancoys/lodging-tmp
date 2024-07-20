import { Component, OnInit } from '@angular/core';

import { Router } from '@angular/router';

import { ContextService, ApiService, AuthService} from 'sb-shared-lib';

import * as $ from 'jquery';
import { type } from 'jquery';


/*
This is the component bootstrapped by app.module.ts
*/

@Component({
  selector: 'app-root',
  templateUrl: './app.root.component.html',
  styleUrls: ['./app.root.component.scss']
})
export class AppRootComponent implements OnInit {

    public show_side_menu: boolean = false;
    public show_side_bar: boolean = true;

    public topMenuItems:any[] = [];
    public navMenuItems: any = [];

    public translationsMenuLeft: any = {};
    public translationsMenuTop: any = {};

    constructor(
        private router: Router,
        private context:ContextService,
        private api:ApiService,
        private auth:AuthService
    ) {}


    public lower_screen_resolution: boolean = false;

    public async ngOnInit() {
        console.debug('AppRootComponent::Bootstrapping Planning App');
        this.lower_screen_resolution = window.innerWidth < 1152;

        window.onresize = () => {
            this.lower_screen_resolution = window.innerWidth < 1152;
            // if lower than 1280 : hide right pane
            // if lower than 1152 : hide both panes
            // if lower than 1024 : show too low resolution notice
        }


        try {
            await this.auth.authenticate();
        }
        catch(response) {
            window.location.href = '/apps';
            return;
        }

    }


}