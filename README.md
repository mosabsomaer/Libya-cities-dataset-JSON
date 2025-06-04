# Libya cities dataset

This repository contains a dataset of all Libyan cities, towns, villages, and hamlets. The dataset includes longitude and latitude coordinates, as well as localized names for each location.

## New Update

In this update, we have expanded our dataset to include not only cities but also towns, villages, and hamlets. This enhancement allows for a more complete representation of Libyan geographical data.

## New Features

- **Multilingual Support**: New language variants of place names from OpenStreetMap
- **Data Merging**: Updates existing entries and adds new ones without duplicates

## Usage

To run the script and update the dataset, use the following command:

```bash
php index.php
```


### Example JSON Format

```json
[
    {
        "id": 1,
        "name": [
            {
                "ar": "طرابلس",
                "en": "Tripoli",
                "fr": "Tripoli",
                "de": "Tripolis",
                "es": "Trípoli",
                "it": "Tripoli",
                "ru": "Триполи"
            }
        ],
        "latitude": 32.8829717,
        "longitude": 13.1708262
    }
]
```

## File Structure

```
.
├── Fetcher.php           # Main processing class
├── index.php             # Application entry point
├── input.json            # Raw OpenStreetMap data (generated)
├── libya-datasets/
│   ├── cities.json       # Processed cities data
│   ├── towns.json        # Processed towns data
│   ├── villages.json      # Processed villages data
│   ├── hamlets.json      # Processed hamlets data
│   ├── all.json          # Combined data of all locations
│   └── missing.json      # Locations that are missing from Overpass API
└── README.md            # This documentation
```