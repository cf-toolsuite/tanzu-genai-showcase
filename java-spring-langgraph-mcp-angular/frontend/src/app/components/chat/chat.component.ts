import { Component, OnInit, ViewChild, ElementRef, AfterViewChecked } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ChatService } from '../../services/chat.service';
import { ChatMessage, MessageType } from '../../models/chat-message.model';
import { ChatSession } from '../../models/chat-session.model';
import { EventCardComponent } from '../event-card/event-card.component';
import { CityInfoComponent } from '../city-info/city-info.component';
import { EventInfo } from '../../models/event-info.model';
import { CityInfo } from '../../models/city-info.model';

@Component({
  selector: 'app-chat',
  standalone: true,
  imports: [CommonModule, FormsModule, EventCardComponent, CityInfoComponent],
  templateUrl: './chat.component.html',
  styleUrls: ['./chat.component.scss']
})
export class ChatComponent implements OnInit, AfterViewChecked {
  @ViewChild('messagesContainer') private messagesContainer!: ElementRef;

  session: ChatSession | null = null;
  currentMessage = '';
  loading = false;
  error = '';

  // Make enum available in template
  MessageType = MessageType;

  // Store temporary local data until backend response
  displayedEvents: EventInfo[] = [];
  displayedCity: CityInfo | null = null;

  constructor(private chatService: ChatService) {}

  ngOnInit(): void {
    this.createNewSession();
  }

  ngAfterViewChecked() {
    this.scrollToBottom();
  }

  createNewSession(): void {
    this.loading = true;
    this.error = '';

    this.chatService.createSession().subscribe({
      next: (session) => {
        this.session = session;
        this.loading = false;
      },
      error: (err) => {
        console.error('Error creating chat session:', err);
        this.error = 'Failed to create chat session. Please try again.';
        this.loading = false;
      }
    });
  }

  sendMessage(): void {
    if (!this.currentMessage.trim() || !this.session) {
      return;
    }

    // Add user message to the UI immediately
    const userMessage: ChatMessage = {
      id: Date.now().toString(), // Temporary ID
      role: 'user',
      content: this.currentMessage,
      timestamp: new Date().toISOString(),
      type: MessageType.TEXT
    };

    this.session.messages.push(userMessage);

    const request = {
      sessionId: this.session.id,
      message: this.currentMessage
    };

    this.currentMessage = '';
    this.loading = true;

    this.chatService.sendMessage(request).subscribe({
      next: (response) => {
        this.loading = false;

        // Check if we need to update the events display
        if (response.recommendedEvents && response.recommendedEvents.length > 0) {
          this.displayedEvents = response.recommendedEvents;
        }

        // Check if we need to update the city info display
        if (response.cityInfo) {
          this.displayedCity = response.cityInfo;
        }

        // Add assistant response to the UI
        this.session?.messages.push(response.message);
      },
      error: (err) => {
        console.error('Error sending message:', err);
        this.error = 'Failed to send message. Please try again.';
        this.loading = false;

        // Add error message to chat
        if (this.session) {
          this.session.messages.push({
            id: Date.now().toString(),
            role: 'assistant',
            content: 'Sorry, there was an error processing your message. Please try again.',
            timestamp: new Date().toISOString(),
            type: MessageType.TEXT
          });
        }
      }
    });
  }

  clearChat(): void {
    this.createNewSession();
    this.displayedEvents = [];
    this.displayedCity = null;
  }

  private scrollToBottom(): void {
    try {
      this.messagesContainer.nativeElement.scrollTop = this.messagesContainer.nativeElement.scrollHeight;
    } catch(err) {
      console.error('Error scrolling to bottom:', err);
    }
  }
}
