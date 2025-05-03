#!/usr/bin/env python3
"""
Generate favicon.ico from SVG using CairoSVG and PIL.
This script requires the following packages:
    - cairosvg
    - pillow
"""

import os
import cairosvg
from PIL import Image
from io import BytesIO

# Ensure the output directory exists
output_dir = os.path.join('static', 'images')
os.makedirs(output_dir, exist_ok=True)

# Path to the SVG file
svg_path = os.path.join(output_dir, 'favicon.svg')

# Define the sizes for the ico file
sizes = [16, 32, 48, 64, 128, 256]
ico_images = []

print(f"Generating favicon.ico from {svg_path}...")

for size in sizes:
    print(f"Processing size {size}x{size}...")

    # Convert SVG to PNG of the specified size
    png_data = cairosvg.svg2png(url=svg_path, output_width=size, output_height=size)

    # Create a PIL Image from the PNG data
    img = Image.open(BytesIO(png_data))
    ico_images.append(img)

# Save as ICO file
ico_path = os.path.join(output_dir, 'favicon.ico')
ico_images[0].save(
    ico_path,
    format='ICO',
    sizes=[(img.width, img.height) for img in ico_images],
    append_images=ico_images[1:]
)

print(f"Favicon.ico generated successfully at {ico_path}")

# Generate PNG images for various uses
for size in [192, 512]:
    print(f"Generating {size}x{size} PNG...")
    png_data = cairosvg.svg2png(url=svg_path, output_width=size, output_height=size)
    with open(os.path.join(output_dir, f'favicon-{size}.png'), 'wb') as f:
        f.write(png_data)

# Create apple-touch-icon
print("Generating Apple touch icon...")
apple_icon_data = cairosvg.svg2png(url=svg_path, output_width=180, output_height=180)
with open(os.path.join(output_dir, 'apple-touch-icon.png'), 'wb') as f:
    f.write(apple_icon_data)

print("All favicon files generated successfully!")
