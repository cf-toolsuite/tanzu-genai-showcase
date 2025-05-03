# Tanzu GenAI Showcase

This repository contains multiple examples of applications that can be deployed on [Tanzu Platform for Cloud Foundry](https://techdocs.broadcom.com/us/en/vmware-tanzu/platform/tanzu-platform-for-cloud-foundry/10-0/tpcf/concepts-overview.html), demonstrating the use of [GenAI tile](https://techdocs.broadcom.com/us/en/vmware-tanzu/platform-services/genai-on-tanzu-platform-for-cloud-foundry/10-0/ai-cf/index.html)'s LLM capabilities.

## Overview

Each subdirectory in this repository represents a different tech stack implementation showcasing how to integrate with GenAI LLM services. These examples cover a variety of use cases, from chatbots and recommendation systems to research assistants and event discovery applications.

## Project Samples

| Link | Description | Languages and Frameworks |
|------|-------------|--------------------------|
| 🚗[![Transportation](https://img.shields.io/badge/-Transportation-blue?style=flat-square&logo=dotnet)](./dotnet-extensions-ai/README.md) | A transportation mode recommendation bot that helps users choose the best way to travel between locations. | .NET, Microsoft.Extensions.AI, Semantic Kernel |
| 🏛️[![Congress API](https://img.shields.io/badge/-Congress_API-blue?style=flat-square&logo=go)](./go-fiber-langchaingo/README.md) | A chatbot that interacts with the Congress.gov API to provide information about bills, amendments, and more. | Go, Fiber, LangChainGo |
| 🎭[![Event Recommendations](https://img.shields.io/badge/-Event_Recommendations-blue?style=flat-square&logo=java)](./java-spring-langgraph-mcp-angular/README.md) | An event recommendation chatbot that suggests events based on user preferences and location. | Java, Spring Boot, LangGraph, Model Context Protocol, Angular |
| ✈️[![Flight Tracking](https://img.shields.io/badge/-Flight_Tracking-blue?style=flat-square&logo=java)](./java-spring-ai-mcp/README.md) | A flight tracking chatbot that provides real-time flight information through a natural language interface. | Java, Spring AI, Model Context Protocol, Vaadin |
| 📰[![News Aggregator](https://img.shields.io/badge/-News_Aggregator-blue?style=flat-square&logo=javascript)](./js-langchain-react/README.md) | A news aggregation application that searches for articles and generates concise summaries. | JavaScript, LangChain, React |
| 💼[![Company Research](https://img.shields.io/badge/-Company_Research-blue?style=flat-square&logo=php)](./php-symfony-neuron/README.md) | A company research application that gathers financial data and generates reports. | PHP, Symfony, Neuron |
| 🎬[![Movie Agent](https://img.shields.io/badge/-Movie_Agent-blue?style=flat-square&logo=python)](./py-django-crewai/README.md) | A movie chatbot that coordinates multiple AI agents to find movies and nearby show times. | Python, Django, CrewAI |
| ✈️[![Flight Tracking](https://img.shields.io/badge/-Flight_Tracking-blue?style=flat-square&logo=ruby)](./ruby-sinatra-fastmcp/README.md) | A flight tracking chatbot with a modern web UI and AI integration that provides real-time flight information. | Ruby, Sinatra, FastMCP |

## Getting Started

Each tech stack implementation includes its own README.md with specific instructions for:

* Prerequisites
* Local development setup
* Building and testing
* Deploying to Tanzu Platform for Cloud Foundry

## Common Structure

All example projects follow a similar structure and address the following aspects:

1. **What is it?** - A description of the application and its capabilities
2. **Prerequisites** - Required tools, dependencies, and environment setup
3. **Building** - How to build and package the application
4. **Local Development** - Running and testing the application locally
5. **Deployment** - Deploying to Tanzu Platform for Cloud Foundry
6. **Tech Stack** - Detailed information about the technologies used
7. **Project Structure** - Explanation of the repository organization

## Deployment on Tanzu Platform for Cloud Foundry

All examples are designed to be deployable with `cf push` and can be bound to a GenAI LLM service instance.

### Service Binding

The examples demonstrate two approaches to consuming LLM services:

1. **Service Instance Binding**: Applications are bound to an LLM service instance, and the credentials are automatically injected.
2. **Manual Configuration**: Service keys are created and the applications are manually configured to use those credentials.

## Contributing

Feel free to add new tech stack examples or improve existing ones.

## Found Elsewhere

Still hungry? Here are some links to other project samples to whet your appetite.

| Link | Description | Languages and Frameworks |
|------|-------------|--------------------------|
| 💬[![CF-MCP-Client](https://img.shields.io/badge/-CF--MCP--Client-blue?style=flat-square&logo=spring)](https://github.com/cpage-pivotal/cf-mcp-client) | A Spring AI-based chatbot application for Cloud Foundry that leverages Model Context Protocol (MCP) to integrate with AI services and external tools through a standardized interface. | Java, Spring Boot, Spring AI, TypeScript, Model Context Protocol |
|🛍️[![Acme Fitness](https://img.shields.io/badge/-Acme_Fitness-blue?style=flat-square&logo=java)](https://github.com/cpage-pivotal/acme-fitness-store) | An online retail store application that demonstrates Spring AI integration with microservices architecture for product recommendations and enhanced shopping experiences. | Java, Spring Boot, Spring AI, Microservices |
| 🎸[![Spring Metal](https://img.shields.io/badge/-Spring_Metal-blue?style=flat-square&logo=spring)](https://github.com/0pens0/spring-metal) | A Spring-based application demonstrating the deployment of AI-enhanced applications to Cloud Foundry with external service bindings. | Java, Spring Boot, Spring AI, Cloud Foundry |
| ♟️[![Chess AI](https://img.shields.io/badge/-Chess_AI-blue?style=flat-square&logo=spring)](https://github.com/alexandreroman/mcp-chess) | A Model Context Protocol (MCP) server for interactive chess gameplay that integrates with Spring AI to enable AI-assisted chess analysis and visualization. | Java, Spring Boot, Spring AI, MCP |
| 🎮[![Kahoot Quiz Generator](https://img.shields.io/badge/-Kahoot_Quiz_Generator-blue?style=flat-square&logo=spring)](https://github.com/pacphi/kahoot-quiz-generator) | A Spring application that leverages AI to generate interactive quiz content for educational purposes. | Java, Spring Boot, Spring AI |
| 📚[![Sanford](https://img.shields.io/badge/-Sanford-blue?style=flat-square&logo=spring)](https://github.com/cf-toolsuite/sanford) | A document management system that utilizes LLMs, a storage bucket, and a Vector store to search for and summarize uploaded documents. | Java, Spring Boot, Spring AI, Vector Databases |
| 🖥️[![Sanford UI](https://img.shields.io/badge/-Sanford_UI-blue?style=flat-square&logo=vaadin)](https://github.com/cf-toolsuite/sanford-ui) | A Vaadin-based user interface companion to the Sanford document management system. | Java, Vaadin, Spring Boot |
| 💬[![Mattermost AI Service](https://img.shields.io/badge/-Mattermost_AI_Service-blue?style=flat-square&logo=mattermost)](https://github.com/pacphi/mattermost-ai-service) | An AI service integration for the Mattermost messaging platform that enhances team collaboration with intelligent features. | Java, Spring Boot, Spring AI, Mattermost API |
| 🔄[![CF Kaizen](https://img.shields.io/badge/-CF_Kaizen-blue?style=flat-square&logo=cloudfoundry)](https://github.com/cf-toolsuite/cf-kaizen) | A tool that enables continuous learning from and chatting with one or more Cloud Foundry Foundations to improve platform management. | Java, Spring Boot, Spring AI, Cloud Foundry |
| 🏭[![Factory MCP Server](https://img.shields.io/badge/-Factory_MCP_Server-blue?style=flat-square&logo=java)](https://github.com/cpage-pivotal/factory-mcp-server) | A simulation of a manufacturing supply chain dashboard that also functions as an MCP Server for natural language queries. | Java, Spring AI, TypeScript |
