# Travel Advisor User Guide

This document provides instructions and information for using the Travel Advisor application.

## Introduction

Travel Advisor is an AI-powered application that helps you find the best transportation options for your journey. By leveraging LLM technology and real-time data from Google Maps, it provides personalized recommendations based on your preferences.

## Getting Started

### Accessing the Application

The Travel Advisor application can be accessed at:

- Local development: `https://localhost:5000`
- Cloud Foundry deployment: The URL provided by your administrator

### Navigating the Interface

The application has a simple navigation menu on the left side:

- **Home**: The landing page with general information about the application
- **Travel Advisor**: The main page where you can get travel recommendations
- **About**: Information about the application, its features, and how it works

## Using the Travel Advisor

### Step 1: Describe Your Journey

1. Navigate to the "Travel Advisor" page
2. In the text area, describe your journey in natural language
   - Include origin and destination
   - Optionally include departure or arrival times
   - Mention any preferences (cost, speed, comfort, environmental impact, etc.)

Example queries:

- "What's the best way to get from Boston to New York tomorrow morning?"
- "I need to travel from Seattle to Portland this weekend. I prefer environmentally friendly options."
- "How should I get from San Francisco to Los Angeles? I need to arrive by 6 PM and cost is my main concern."

### Step 2: Review and Select Recommendations

After submitting your query:

1. The system will analyze your request and extract key details
2. You'll see available transportation options ranked by overall score
3. The recommended option (highest score) will be selected automatically
4. You can click on any option to view its details

### Step 3: Explore Recommendation Details

For each recommendation, you'll see:

- **Journey Details**: Distance, duration, and estimated cost
- **Scores**: Environmental impact, convenience, and preference match ratings
- **Pros and Cons**: Benefits and drawbacks of this transportation mode
- **Explanation**: Why this option was recommended based on your preferences
- **Journey Steps**: Detailed breakdown of the journey (if applicable)

### Step 4: Ask Follow-up Questions

If you have questions about a specific recommendation:

1. Scroll down to the "Ask a Follow-up Question" section
2. Type your question in the text area
3. Click "Ask Question" to get an AI-generated response

Example follow-up questions:

- "How would the duration change during rush hour?"
- "What's the environmental impact compared to other options?"
- "Are there any alternatives with fewer transfers?"

## Tips for Better Results

- **Be Specific**: Include clear origin and destination locations
- **Mention Preferences**: State what factors are most important to you (time, cost, comfort, etc.)
- **Provide Context**: Mention any special circumstances (traveling with luggage, accessibility needs, etc.)
- **Use Natural Language**: Write as if you're asking a human travel advisor

## Privacy and Data Usage

- The application processes your travel queries to provide recommendations
- Your queries and preferences are not stored permanently
- No personal account or login information is required
- The application uses the Google Maps API and may be subject to Google's terms of service

## Troubleshooting

### Common Issues

1. **No Results**: If no recommendations appear, try:
   - Checking that your origin and destination are valid, recognizable locations
   - Making your query more specific
   - Ensuring you have a stable internet connection

2. **Slow Response**: During peak usage or with complex queries:
   - Wait for processing to complete
   - Try simplifying your query
   - Try again later if the system is experiencing high load

3. **Unexpected Recommendations**: If recommendations don't match your expectations:
   - Check if your preferences were clearly stated
   - Ask follow-up questions for clarification
   - Try rephrasing your query to emphasize different aspects

### Getting Help

If you encounter persistent issues with the application, please contact your system administrator or IT support team.

## Feedback

Your feedback helps improve the application. If you have suggestions or encounter problems, please share them with your administrator.
