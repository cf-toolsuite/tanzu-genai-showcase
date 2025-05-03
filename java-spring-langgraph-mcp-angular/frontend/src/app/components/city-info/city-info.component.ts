import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CityInfo } from '../../models/city-info.model';

@Component({
  selector: 'app-city-info',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './city-info.component.html',
  styleUrls: ['./city-info.component.scss']
})
export class CityInfoComponent {
  @Input() cityInfo!: CityInfo;

  formatPopulation(population: number): string {
    if (population >= 1000000) {
      return (population / 1000000).toFixed(1) + ' million';
    } else if (population >= 1000) {
      return (population / 1000).toFixed(1) + 'k';
    }
    return population.toString();
  }
}
