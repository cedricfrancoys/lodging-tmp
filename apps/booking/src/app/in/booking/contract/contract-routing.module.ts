import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

import { BookingContractComponent } from './contract.component';
import { BookingContractRemindComponent } from './remind/remind.component';


const routes: Routes = [
    {
        path: 'remind',
        component: BookingContractRemindComponent
    },
    // wildcard route (accept root and any sub route that does not match any of the routes above)
    {
        path: '**',
        component: BookingContractComponent
    }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class BookingContractRoutingModule {}
