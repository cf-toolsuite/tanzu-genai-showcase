package handler

import (
	"context"
	"time"

	"github.com/cf-toolsuite/tanzu-genai-showcase/go-fiber-langchaingo/internal/service"
	"github.com/gofiber/fiber/v2"
)

// Handler holds the HTTP handlers for the application
type Handler struct {
	chatbotService *service.ChatbotService
}

// NewHandler creates a new Handler
func NewHandler(chatbotService *service.ChatbotService) *Handler {
	return &Handler{
		chatbotService: chatbotService,
	}
}

// ChatRequest represents a chat request from the user
type ChatRequest struct {
	Message string `json:"message"`
}

// ChatResponse represents a response to a chat request
type ChatResponse struct {
	Response string `json:"response"`
}

// HistoryResponse represents the conversation history
type HistoryResponse struct {
	History []map[string]string `json:"history"`
}

// HandleHealthCheck handles health check requests
func (h *Handler) HandleHealthCheck(c *fiber.Ctx) error {
	return c.Status(fiber.StatusOK).JSON(fiber.Map{
		"status": "ok",
		"time":   time.Now().Format(time.RFC3339),
	})
}

// HandleChat handles chat requests
func (h *Handler) HandleChat(c *fiber.Ctx) error {
	var req ChatRequest
	if err := c.BodyParser(&req); err != nil {
		return c.Status(fiber.StatusBadRequest).JSON(fiber.Map{
			"error": "Invalid request body",
		})
	}

	if req.Message == "" {
		return c.Status(fiber.StatusBadRequest).JSON(fiber.Map{
			"error": "Message cannot be empty",
		})
	}

	// Create a context with timeout for the LLM
	ctx, cancel := context.WithTimeout(c.Context(), 60*time.Second)
	defer cancel()

	// Check if we should use tool calling
	useTools := c.Query("useTools", "false") == "true"

	var response string
	var err error

	if useTools {
		// Process the user's message with tool calling
		response, err = h.chatbotService.ProcessUserQueryWithTools(ctx, req.Message)
	} else {
		// Process the user's message with the standard approach
		response, err = h.chatbotService.ProcessUserQuery(ctx, req.Message)
	}

	if err != nil {
		return c.Status(fiber.StatusInternalServerError).JSON(fiber.Map{
			"error": err.Error(),
		})
	}

	return c.Status(fiber.StatusOK).JSON(ChatResponse{
		Response: response,
	})
}

// HandleGetHistory handles requests for the conversation history
func (h *Handler) HandleGetHistory(c *fiber.Ctx) error {
	history := h.chatbotService.GetConversationHistory()
	return c.Status(fiber.StatusOK).JSON(HistoryResponse{
		History: history,
	})
}

// HandleClearHistory handles requests to clear the conversation history
func (h *Handler) HandleClearHistory(c *fiber.Ctx) error {
	h.chatbotService.ClearConversation()
	return c.Status(fiber.StatusOK).JSON(fiber.Map{
		"status": "Conversation history cleared",
	})
}

// RegisterRoutes registers the handler routes with the Fiber app
func (h *Handler) RegisterRoutes(app *fiber.App) {
	// API routes
	api := app.Group("/api")
	api.Get("/health", h.HandleHealthCheck)
	api.Post("/chat", h.HandleChat)
	api.Get("/history", h.HandleGetHistory)
	api.Post("/clear", h.HandleClearHistory)

	// Serve static files from the public directory
	app.Static("/", "./public")

	// For single page applications, serve index.html for any other route
	app.Get("/*", func(c *fiber.Ctx) error {
		return c.SendFile("./public/index.html")
	})
}
