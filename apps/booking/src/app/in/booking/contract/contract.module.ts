import { NgModule } from '@angular/core';
import { DateAdapter, MAT_DATE_LOCALE } from '@angular/material/core';
import { Platform } from '@angular/cdk/platform';

import { SharedLibModule, CustomDateAdapter } from 'sb-shared-lib';

import { BookingContractRoutingModule } from './contract-routing.module';

import { BookingContractComponent } from './contract.component';
import { BookingContractRemindComponent } from './remind/remind.component';

@NgModule({
  imports: [
    SharedLibModule,
    BookingContractRoutingModule
  ],
  declarations: [
    BookingContractComponent,
    BookingContractRemindComponent
  ],
  providers: [
    { provide: DateAdapter, useClass: CustomDateAdapter, deps: [MAT_DATE_LOCALE, Platform] }
  ]
})
export class AppInBookingContractModule { }
