package main

import (
	"fmt"
	"io"
	"log"
	"os"
	"os/signal"
	"runtime/debug"
	"strings"
	"syscall"

	"github.com/gofiber/fiber/v2"
	"github.com/gofiber/fiber/v2/middleware/cors"
	fiberLogger "github.com/gofiber/fiber/v2/middleware/logger"
	"github.com/gofiber/fiber/v2/middleware/recover"
	"github.com/gofiber/template/html/v2"

	"github.com/cf-toolsuite/tanzu-genai-showcase/go-fiber-langchaingo/api"
	"github.com/cf-toolsuite/tanzu-genai-showcase/go-fiber-langchaingo/config"
	"github.com/cf-toolsuite/tanzu-genai-showcase/go-fiber-langchaingo/internal/handler"
	"github.com/cf-toolsuite/tanzu-genai-showcase/go-fiber-langchaingo/internal/service"
	"github.com/cf-toolsuite/tanzu-genai-showcase/go-fiber-langchaingo/pkg/llm"
	"github.com/cf-toolsuite/tanzu-genai-showcase/go-fiber-langchaingo/pkg/logger"
)

func main() {
	// Load configuration
	cfg, err := config.LoadConfig()
	if err != nil {
		log.Fatalf("Failed to load configuration: %v", err)
	}

	// Create Congress.gov API client
	congressClient := api.NewCongressClient(cfg.CongressAPIKey)

	// Create LLM client
	llmClient, err := llm.NewLLMClient(cfg.LLMAPIKey, cfg.LLMAPIURL, cfg.LLMModel)
	if err != nil {
		log.Fatalf("Failed to create LLM client: %v", err)
	}

	// Create chatbot service
	chatbotService := service.NewChatbotService(congressClient, llmClient)
	chatbotService.Initialize()

	// Create handler
	h := handler.NewHandler(chatbotService)

	// Check if the public directory exists, create it if it doesn't
	if _, err := os.Stat("public"); os.IsNotExist(err) {
		if err := os.Mkdir("public", 0755); err != nil {
			log.Fatalf("Failed to create public directory: %v", err)
		}

		// Create a basic index.html file if it doesn't exist
		if _, err := os.Stat("public/index.html"); os.IsNotExist(err) {
			indexHTML := `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Congress.gov Chatbot</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .chat-container {
            height: calc(100vh - 200px);
            overflow-y: auto;
        }
        .user-message {
            background-color: #e2f3ff;
            border-radius: 1rem;
            padding: 0.5rem 1rem;
            max-width: 80%;
            margin-left: auto;
            margin-right: 1rem;
        }
        .assistant-message {
            background-color: #f0f0f0;
            border-radius: 1rem;
            padding: 0.5rem 1rem;
            max-width: 80%;
            margin-right: auto;
            margin-left: 1rem;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="container mx-auto p-4">
        <header class="bg-blue-600 text-white p-4 rounded-t-lg shadow">
            <h1 class="text-2xl font-bold">Congress.gov Chatbot</h1>
            <p class="text-sm">Ask questions about bills, legislation, members of Congress, and more.</p>
        </header>

        <div class="bg-white rounded-b-lg shadow p-4 mb-4">
            <div id="chat-container" class="chat-container flex flex-col space-y-4 mb-4">
                <div class="assistant-message">
                    Hello! I'm your Congress.gov chatbot assistant. How can I help you today? You can ask me about bills, legislation, members of Congress, and more.
                </div>
            </div>

            <div class="flex flex-col space-y-2">
                <div class="flex space-x-2">
                    <input type="text" id="user-input" placeholder="Type your message here..."
                        class="flex-grow p-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <button id="send-button" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Send
                    </button>
                    <button id="clear-button" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                        Clear
                    </button>
                </div>
                <div class="flex items-center">
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="use-tools-toggle" class="sr-only peer">
                        <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        <span class="ml-3 text-sm font-medium text-gray-900">Use API Tools</span>
                    </label>
                </div>
            </div>
        </div>

        <footer class="text-center text-gray-500 text-sm">
            <p>Powered by Tanzu GenAI and Congress.gov API</p>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chatContainer = document.getElementById('chat-container');
            const userInput = document.getElementById('user-input');
            const sendButton = document.getElementById('send-button');
            const clearButton = document.getElementById('clear-button');

            // Function to add a message to the chat container
            function addMessage(content, isUser) {
                const messageDiv = document.createElement('div');
                messageDiv.className = isUser ? 'user-message' : 'assistant-message';
                messageDiv.textContent = content;
                chatContainer.appendChild(messageDiv);
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }

            // Get the tools toggle
            const useToolsToggle = document.getElementById('use-tools-toggle');

            // Function to send a message to the API
            async function sendMessage(message) {
                try {
                    // Check if tools should be used
                    const useTools = useToolsToggle.checked;

                    // Add query parameter if tools should be used
                    const url = useTools ? '/api/chat?useTools=true' : '/api/chat';

                    // Add loading indicator
                    const loadingId = 'loading-' + Date.now();
                    addMessage('Thinking...', false);
                    const loadingElement = chatContainer.lastChild;
                    loadingElement.id = loadingId;

                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ message }),
                    });

                    if (!response.ok) {
                        throw new Error('API request failed');
                    }

                    const data = await response.json();

                    // Remove loading indicator
                    const loadingMessage = document.getElementById(loadingId);
                    if (loadingMessage) {
                        chatContainer.removeChild(loadingMessage);
                    }

                    // Add the actual response
                    addMessage(data.response, false);
                } catch (error) {
                    console.error('Error sending message:', error);
                    addMessage('Sorry, I encountered an error. Please try again.', false);
                }
            }

            // Function to clear the chat history
            async function clearChat() {
                try {
                    const response = await fetch('/api/clear', {
                        method: 'POST',
                    });

                    if (!response.ok) {
                        throw new Error('API request failed');
                    }

                    // Clear the chat container
                    chatContainer.innerHTML = '';
                    addMessage('Hello! I\'m your Congress.gov chatbot assistant. How can I help you today? You can ask me about bills, legislation, members of Congress, and more.', false);
                } catch (error) {
                    console.error('Error clearing chat:', error);
                }
            }

            // Event listener for send button
            sendButton.addEventListener('click', function() {
                const message = userInput.value.trim();
                if (message) {
                    addMessage(message, true);
                    sendMessage(message);
                    userInput.value = '';
                }
            });

            // Event listener for enter key
            userInput.addEventListener('keypress', function(event) {
                if (event.key === 'Enter') {
                    const message = userInput.value.trim();
                    if (message) {
                        addMessage(message, true);
                        sendMessage(message);
                        userInput.value = '';
                    }
                }
            });

            // Event listener for clear button
            clearButton.addEventListener('click', clearChat);

            // Load chat history on page load
            async function loadChatHistory() {
                try {
                    const response = await fetch('/api/history');
                    if (response.ok) {
                        const data = await response.json();

                        // Clear existing messages
                        chatContainer.innerHTML = '';

                        // Add messages from history
                        data.history.forEach(msg => {
                            if (msg.role !== 'system') {
                                addMessage(msg.content, msg.role === 'user');
                            }
                        });

                        // If no messages, add a welcome message
                        if (data.history.length === 0 || data.history.every(msg => msg.role === 'system')) {
                            addMessage('Hello! I\'m your Congress.gov chatbot assistant. How can I help you today? You can ask me about bills, legislation, members of Congress, and more.', false);
                        }
                    }
                } catch (error) {
                    console.error('Error loading chat history:', error);
                }
            }

            // Load chat history
            loadChatHistory();
        });
    </script>
</body>
</html>`
			if err := os.WriteFile("public/index.html", []byte(indexHTML), 0644); err != nil {
				log.Fatalf("Failed to create index.html: %v", err)
			}
		}
	}

	// Create Fiber app with HTML template engine
	engine := html.New("./public", ".html")
	app := fiber.New(fiber.Config{
		Views: engine,
		ErrorHandler: func(c *fiber.Ctx, err error) error {
			// Return a custom error message
			return c.Status(fiber.StatusInternalServerError).JSON(fiber.Map{
				"error": err.Error(),
			})
		},
	})

	// Create logs directory if it doesn't exist
	if _, err := os.Stat("logs"); os.IsNotExist(err) {
		if err := os.Mkdir("logs", 0755); err != nil {
			log.Fatalf("Failed to create logs directory: %v", err)
		}
	}

	// Set up a log file for HTTP requests
	logFile, err := os.OpenFile("logs/http.log", os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0666)
	if err != nil {
		log.Fatalf("Failed to create log file: %v", err)
	}

	// Initialize our custom logger
	if err := logger.Init(); err != nil {
		log.Fatalf("Failed to initialize logger: %v", err)
	}

	// Log startup information
	logger.LogStartup(map[string]string{
		"port":             fmt.Sprintf("%d", cfg.Port),
		"environment":      cfg.Environment,
		"llm_model":        cfg.LLMModel,
		"llm_api_url":      cfg.LLMAPIURL,
		"llm_api_key":      cfg.LLMAPIKey,
		"congress_api_key": cfg.CongressAPIKey,
	})

	// Enhanced logger configuration with more details
	app.Use(fiberLogger.New(fiberLogger.Config{
		// Log format with extended details
		Format:     "${time} | ${status} | ${latency} | ${method} ${path} | ${ip} | ${reqHeader:Content-Type} | ${reqHeader:User-Agent} | ${resBody} | ${error}\n",
		TimeFormat: "2006-01-02 15:04:05",
		TimeZone:   "Local",
		// Output to both console and file
		Output: io.MultiWriter(os.Stdout, logFile),
		// Log headers
		Next: func(c *fiber.Ctx) bool {
			// Skip logging for static files to reduce noise
			return c.Path() == "/favicon.ico"
		},
	}))

	// Set up custom recovery handler with detailed error logging
	app.Use(recover.New(recover.Config{
		EnableStackTrace: true,
		StackTraceHandler: func(c *fiber.Ctx, e interface{}) {
			log.Printf("PANIC RECOVERED: %v\nStack Trace: %s\n", e, debug.Stack())
		},
	}))

	app.Use(cors.New())

	// Add a middleware to log request and response bodies for API endpoints
	app.Use(func(c *fiber.Ctx) error {
		// Only log API requests
		if strings.HasPrefix(c.Path(), "/api") {
			// Log request
			reqBody := string(c.Request().Body())
			if reqBody != "" {
				log.Printf("REQUEST BODY [%s %s]: %s", c.Method(), c.Path(), reqBody)
			}

			// Save original handler
			err := c.Next()

			// Log response body after handler has processed the request
			if len(c.Response().Body()) > 0 {
				// Truncate very long responses
				respBody := string(c.Response().Body())
				if len(respBody) > 1000 {
					respBody = respBody[:1000] + "... [truncated]"
				}
				log.Printf("RESPONSE BODY [%s %s] [Status: %d]: %s",
					c.Method(), c.Path(), c.Response().StatusCode(), respBody)
			}

			return err
		}
		return c.Next()
	})

	// Register routes
	h.RegisterRoutes(app)

	// Start server
	go func() {
		addr := fmt.Sprintf(":%d", cfg.Port)
		if err := app.Listen(addr); err != nil {
			log.Fatalf("Error starting server: %v", err)
		}
	}()

	// Wait for interrupt signal to gracefully shutdown the server
	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit

	log.Println("Shutting down server...")
	if err := app.Shutdown(); err != nil {
		log.Fatalf("Error shutting down server: %v", err)
	}
}
