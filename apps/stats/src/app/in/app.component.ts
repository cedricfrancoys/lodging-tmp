import { Component, OnInit, NgZone  } from '@angular/core';
import { ContextService } from 'sb-shared-lib';

@Component({
  selector: 'app',
  templateUrl: 'app.component.html',
  styleUrls: ['app.component.scss']
})
export class AppComponent implements OnInit  {


    public ready: boolean = false;
    public context_open: boolean = false;

    constructor(
        private context: ContextService,
        private zone: NgZone
    ) {}


    public ngOnInit() {
        console.log('AppComponent::ngOnInit');

        this.context.ready.subscribe( (ready:boolean) => {
            this.ready = ready;
        });

    }

}